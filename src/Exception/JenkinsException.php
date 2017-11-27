<?php

namespace JenkinsAPI\Exception;

class JenkinsException extends \RuntimeException
{
    public function __construct($reponse, $code = 0)
    {
        $message = '';

        if (!empty($reponse)) {
            // 'disable' libxml error handling
            // throws warnings for malformed HTML otherwise
            libxml_use_internal_errors(true);

            $dom = new \DOMDocument();
            $dom->loadHTML($reponse);
            $xPath = new \DOMXPath($dom);

            $errorMsgElem = $xPath->query('body/p');
            // it may have been intended to be inside the 'p' tag,
            // but as it's invalid it closes the 'p' tag before and 'pre' comes just after it
            $errorReasonElem = $xPath->query('body/pre');

            if ($errorMsgElem->length && $errorReasonElem->length) {
                $errorMsg = $errorMsgElem->item(0)->nodeValue;
                $errorReason = $errorReasonElem->item(0)->nodeValue;

                $message = trim(preg_replace('/\s+/', ' ', $errorMsg . $errorReason));
            } else {
                $message = 'Unparsable Error';
            }

            libxml_clear_errors();
        }

        parent::__construct($message, $code);
    }
}
