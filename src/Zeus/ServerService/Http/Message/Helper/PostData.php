<?php

namespace Zeus\ServerService\Http\Message\Helper;

use Zeus\ServerService\Http\Message\Request;

trait PostData
{
    protected function parseRequestPostData(Request $request)
    {
        if ($request->getHeaderOverview('Content-Type', true) !== 'application/x-www-form-urlencoded') {
            return;
        }

        $requestPost = $request->getPost();

        $body = $request->getContent();

        while (false !== ($pos = strpos($body, "&", $this->cursorPositionInRequestPostBody)) || $this->bodyReceived) {
            $paramsLength = $pos === false ? strlen($body) : $pos;
            $postParameter = substr($body, $this->cursorPositionInRequestPostBody, $paramsLength - $this->cursorPositionInRequestPostBody);
            $postArray = [];
            parse_str($postParameter, $postArray);
            $paramName = key($postArray);
            if (is_array($postArray[$paramName])) {
                $postArray[$paramName] = array_merge((array) $requestPost->get($paramName), $postArray[$paramName]);
            }

            $requestPost->set($paramName, $postArray[$paramName]);

            $this->cursorPositionInRequestPostBody = $pos + 1;

            if ($pos === false) {
                break;
            }
        }
    }
}