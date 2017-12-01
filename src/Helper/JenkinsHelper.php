<?php

namespace JenkinsAPI\Helper;

use JenkinsAPI\Exception\JenkinsException;

class JenkinsHelper
{
    private $baseUrl;
    private $curlHandler;

    public function __construct($jenkinsUrl, $user = null, $password = null)
    {
        if (!empty($user) && $password) {
            $jenkinsUrl = str_replace('//', "//$user:$password@", $jenkinsUrl);
        }

        $this->baseUrl = $jenkinsUrl;

        $ch = curl_init();

        // needed for self-signed HTTPS/SSL certificates,
        // else the default check of curl doesn't pass
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $this->curlHandler = $ch;
    }

    /**
     * This function runs the job specified
     *
     * @param string $jobName
     * @return string
     */
    public function build($jobName)
    {
        $url = sprintf('%s/job/%s/build', $this->baseUrl, rawurlencode($jobName));

        curl_setopt($this->curlHandler, CURLOPT_POST, true);
        curl_setopt($this->curlHandler, CURLOPT_HEADER, true);

        return $this->execute($url);
    }

    /**
     * Returns data of the build with given number or being referenced by given queue item URL
     *
     * @param string|int  $build
     * @param string|null $jobName  needed when first parameter is the build number
     * @return object|null
     * @throws \Exception
     */
    public function getBuildData($build, $jobName = null)
    {
        $startTime = time();
        // timeout in seconds
        $timeout = 180;

        if (!is_numeric($build)) {
            $waiting = true;

            while ($waiting && (time() - $startTime) < $timeout) {
                $url = $this->baseUrl . parse_url($build, PHP_URL_PATH) . 'api/json';

                $queueItem = json_decode($this->execute($url));
                $waiting = !preg_match('/\$LeftItem$/', $queueItem->_class);

                if ($waiting) {
                    sleep(1);
                }
            }

            $build = $queueItem->executable->number;
            $jobName = $queueItem->task->name;
        }

        $building = true;

        while ($building && (time() - $startTime) < $timeout) {
            $buildData = json_decode($this->getApiData("$jobName#$build"));
            $building = $buildData->building;

            if ($building) {
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
                    $errorMsg = 'Curl error: ' . curl_error($this->curlHandler);
            }

            throw new \Exception($errorMsg, $curlError);
        }

        return $response;
    }
}
