<?php

namespace Zeus\ServerService\Http\Message\Helper;

use Zend\Http\Response;
use Zeus\ServerService\Http\Message\Request;
use Zend\Validator\Hostname as HostnameValidator;

trait Header
{
    /** @var mixed[] */
    private $serverHostCache = [];

    /** @var string */
    private $headers = null;

    /**
     * @param Request $request
     * @param $connectionServerAddress
     * @return $this
     */
    protected function setHost(Request $request, $connectionServerAddress)
    {
        $fullHost = $request->getHeaderOverview('Host');

        if ($request->getVersion() === Request::VERSION_11) {
            if (!$fullHost) {
                throw new \InvalidArgumentException("Missing host header", Response::STATUS_CODE_400);
            }
        }

        // URI host & port
        $host = null;

        if (isset($this->serverHostCache[$fullHost])) {
            $currentUri = $request->getUri();
            $cachedUri = $this->serverHostCache[$fullHost];
            $currentUri->setHost($cachedUri['host']);
            $currentUri->setPort($cachedUri['port']);

            return $this;
        }

        // Set the host
        if ($fullHost) {
            // works for regname, IPv4 & IPv6
            if (preg_match('~\:(\d+)$~', $fullHost, $matches)) {
                $host = substr($fullHost, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int) $matches[1];
            }

            // set up a validator that check if the hostname is legal (not spoofed)
            $hostnameValidator = new HostnameValidator([
                'allow'       => HostnameValidator::ALLOW_ALL,
                'useIdnCheck' => false,
                'useTldCheck' => false,
            ]);
            // If invalid. Reset the host & port
            if (!$hostnameValidator->isValid($host)) {
                $host = null;
                $port = null;
            } else {
                $this->serverHostCache[$fullHost] = [
                    'host' => $host,
                    'port' => $port
                ];
            }
        }

        if (!$host) {
            if (preg_match('|\:(\d+)$|', $connectionServerAddress, $matches)) {
                $host = substr($connectionServerAddress, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int)$matches[1];

                $this->serverHostCache[$fullHost] = [
                    'host' => $host,
                    'port' => $port
                ];
            }
        }

        $uri = $request->getUri();
        $uri->setHost($host);
        $uri->setPort($port);

        return $this;
    }

    /**
     * @param $message
     * @return bool|Request object if all headers had been processed, false if message is still incomplete
     */
    protected function parseRequestHeaders(& $message)
    {
        $this->headers .= $message;

        if (($pos = strpos($this->headers, "\r\n\r\n")) === false) {
            $message = '';

            if ($pos < strlen($this->headers)) {
                $message = substr($this->headers, $pos + 4);
            }

            return false;
        } else {
            $message = substr($this->headers, $pos + 4);
            $this->headers = substr($this->headers, 0, $pos + 4);
        }

        try {
            $request = Request::fromStringOfHeaders($this->headers);
            $request->getUri()->setScheme('http');
            $this->headers = null;

            return $request;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Incorrect headers: ' . $this->headers, Response::STATUS_CODE_400);
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isBodyAllowedInRequest(Request $request)
    {
        switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    protected function isBodyAllowedInResponse(Request $request)
    {
        switch ($request->getMethod()) {
            case 'OPTIONS':
            case 'HEAD':
            case 'TRACE':
                return false;
            default:
                return true;
        }
    }

    protected function getEncodingType(Request $request)
    {
        $transferEncoding = $request->getHeaderOverview('Transfer-Encoding', true);

        switch ($transferEncoding) {
            case 'chunked':
                return $transferEncoding;
            case null:
                return 'identity';
            default:
                throw new \InvalidArgumentException("Not supported transfer encoding", Response::STATUS_CODE_501);
        }
    }
}