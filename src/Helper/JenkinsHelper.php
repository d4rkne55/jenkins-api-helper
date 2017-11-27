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
     */
    public function build($jobName)
    {
        $url = sprintf('%s/job/%s/build', $this->baseUrl, rawurlencode($jobName));

        curl_setopt($this->curlHandler, CURLOPT_POST, true);

        $this->execute($url);
    }

    /**
     * Returns data of the target provided by the API
     *
     * @param string|null $target  job, @view or job@view
     * @param string      $format  xml|json|python [optional]
     * @return mixed
     */
    public function getApiData($target = null, $format = 'json')
    {
        $targetUrlPart = '';

        if ($target != null) {
            $target = explode('@', $target);
            $targetUrlPart = array();

            if (!empty($target[1])) {
                $targetUrlPart[] = 'view';
                $targetUrlPart[] = rawurlencode($target[1]);
            }

            if (!empty($target[0])) {
                $targetUrlPart[] = 'job';
                $targetUrlPart[] = rawurlencode($target[0]);
            }

            $targetUrlPart = implode('/', $targetUrlPart);
        }

        $url = "$this->baseUrl/$targetUrlPart/api/$format";

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
                    $errorMsg = curl_error($this->curlHandler);
            }

            throw new \Exception($errorMsg, $curlError);
        }

        curl_close($this->curlHandler);

        return $response;
    }
}
