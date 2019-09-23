<?php

namespace JenkinsAPI\Helper;

use JenkinsAPI\Exception\JenkinsException;

/**
 * This class is a helper class for using the Jenkins API
 */
class JenkinsHelper
{
    private $baseUrl;
    private $curlHandler;
    private $responseHeaders = array();

    /**
     * @param string      $jenkinsUrl
     * @param string|null $user
     * @param string|null $password
     * @param bool        $certificateCheck
     */
    public function __construct($jenkinsUrl, $user = null, $password = null, $certificateCheck = true)
    {
        if (!empty($user) && $password) {
            $jenkinsUrl = str_replace('//', "//$user:$password@", $jenkinsUrl);
        }

        $this->baseUrl = $jenkinsUrl;

        $ch = curl_init();

        if (!$certificateCheck) {
            // needed for self-signed HTTPS/SSL certificates,
            // else the default check of curl doesn't pass
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $this->curlHandler = $ch;
    }

    /**
     * This function launches the job specified
     *
     * @param string $jobName
     * @throws \Exception
     */
    public function build($jobName)
    {
        $url = sprintf('%s/job/%s/build', $this->baseUrl, rawurlencode($jobName));

        curl_setopt($this->curlHandler, CURLOPT_POST, true);
        curl_setopt($this->curlHandler, CURLOPT_HEADERFUNCTION, function($ch, $header) {
            $len = strlen($header);
            $header = explode(':', $header, 2);

            // ignore invalid headers (without value)
            if (count($header) < 2) {
                return $len;
            }

            $key = strtolower($header[0]);
            $this->responseHeaders[$key] = trim($header[1]);

            // the header function should return the header length
            return $len;
        });

        $this->execute($url);
    }

    /**
     * Waits for the build to finish and returns data of the build
     * with given number or being referenced by given queue item URL
     *
     * @param string|int  $build    either queue item URL (string) or build number (int)
     * @param string|null $jobName  needed when first parameter is the build number
     * @param int         $timeout
     *
     * @return object|null
     * @throws \Exception
     */
    public function getBuildData($build, $jobName = null, $timeout = 180)
    {
        $startTime = time();

        // deactivate the default output buffer
        ob_end_flush();

        if (!is_numeric($build)) {
            $waiting = true;

            while ($waiting && (time() - $startTime) < $timeout) {
                $url = $this->baseUrl . parse_url($build, PHP_URL_PATH) . 'api/json';

                $queueItem = json_decode($this->execute($url));
                $waiting = !preg_match('/\$LeftItem$/', $queueItem->_class);

                if ($waiting) {
                    // send a response to the webserver once in a while, so it doesn't time out
                    echo " ";
                    flush();

                    sleep(1);
                }
            }

            if ($waiting) {
                throw new \Exception("The script for getting the build status timed out after waiting $timeout seconds for the build to leave the queue.");
            }

            if ($queueItem->cancelled) {
                throw new \Exception('The build got already canceled while waiting in the queue.');
            }

            $build = $queueItem->executable->number;
            $jobName = $queueItem->task->name;
        }

        $building = true;
        $buildData = null;

        while ($building && (time() - $startTime) < $timeout) {
            $buildData = json_decode($this->getApiData("$jobName#$build"));
            $building = $buildData->building;

            if ($building) {
                echo " ";
                flush();

                sleep(5);
            }
        }

        if ($building) {
            throw new \Exception("The script for getting the build status timed out after waiting $timeout seconds for the build to finish.");
        }

        return $buildData;
    }

    /**
     * Returns data of the target provided by the API
     *
     * @param string|null $target  job, @view or job#buildNr
     * @param string      $format  xml|json|python [optional]
     * @return mixed
     * @throws \Exception
     */
    public function getApiData($target = null, $format = 'json')
    {
        $targetUrlPart = '';

        if ($target != null) {
            if (($viewPos = strpos($target, '@')) !== false) {
                $targetUrlPart .= sprintf('/view/%s', rawurlencode(substr($target, $viewPos)));
            } else {
                $target = explode('#', $target);

                $target[0] = rawurlencode($target[0]);
                $target = implode('/', $target);

                $targetUrlPart .= sprintf('/job/%s', $target);
            }
        }

        $url = "{$this->baseUrl}{$targetUrlPart}/api/$format";

        return $this->execute($url);
    }

    /**
     * Getter for the curl response headers
     *
     * @param string $key
     * @return string|null
     */
    public function getResponseHeader($key)
    {
        if (array_key_exists($key, $this->responseHeaders)) {
            return $this->responseHeaders[$key];
        } else {
            return null;
        }
    }

    /**
     * An internal helper function for executing the curl
     *
     * @param string $url
     * @return mixed
     * @throws JenkinsException|\Exception
     */
    private function execute($url)
    {
        // let curl_exec() return the response instead of directly outputting it
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandler, CURLOPT_URL, $url);

        $response = curl_exec($this->curlHandler);
        $statusCode = curl_getinfo($this->curlHandler, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($this->curlHandler);

        if ($statusCode >= 400) {
            throw new JenkinsException($response, $statusCode);
        } elseif ($curlError) {
            switch ($curlError) {
                case CURLE_COULDNT_CONNECT:
                    $errorMsg = 'Jenkins connection failed';
                    break;
                default:
                    $errorMsg = curl_error($this->curlHandler);
            }

            throw new \Exception($errorMsg, $curlError);
        }

        return $response;
    }
}
