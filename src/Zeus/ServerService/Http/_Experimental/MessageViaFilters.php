<?php

namespace Zeus\ServerService\Http\Http\Message;

use Zeus\ServerService\Http\Shared\React\HeartBeatMessageInterface;
use Zeus\ServerService\Http\Shared\React\MessageComponentInterface;
use Zeus\ServerService\Http\Shared\React\ConnectionInterface;
use Zeus\ServerService\Http\Http\Message\Request;
use Zend\Http\Header\Connection;
use Zend\Http\Header\ContentEncoding;
use Zend\Http\Header\ContentLength;
use Zend\Http\Header\GenericHeader;
use Zend\Http\Header\TransferEncoding;
use Zend\Http\Header\Vary;
use Zend\Http\Response;
use Zend\Validator\Hostname as HostnameValidator;

class MessageViaFilters implements MessageComponentInterface, HeartBeatMessageInterface
{
    const ENCODING_IDENTITY = 'identity';
    const ENCODING_CHUNKED = 'chunked';

    const REQUEST_PHASE_IDLE = 1;
    const REQUEST_PHASE_KEEP_ALIVE = 2;
    const REQUEST_PHASE_READING = 4;
    const REQUEST_PHASE_PROCESSING = 8;
    const REQUEST_PHASE_SENDING = 16;

    /** @var int */
    protected $requestPhase = self::REQUEST_PHASE_IDLE;

    /** @var int */
    protected $bufferSize = 655360;

    /** @var callable */
    protected $errorHandler;

    /** @var Callback */
    protected $dispatcher;

    /** @var bool */
    protected $headersSent = false;

    /** @var int */
    protected $keepAliveCount = 100;

    /** @var int */
    protected $keepAliveTimer = 5;

    /** @var bool */
    protected $isChunkedResponse = false;

    /** @var TransferEncoding */
    protected $chunkedHeader;

    /** @var Connection */
    protected $closeConnectionHeader;

    /** @var Connection */
    protected $keepAliveConnectionHeader;

    /** @var bool */
    protected $isKeepAliveConnection = false;

    /** @var string */
    protected $body = '';

    /** @var string */
    protected $buffer = '';

    /** @var bool */
    protected $requestComplete = false;

    /** @var bool */
    protected $headersReceived = false;

    /** @var bool */
    protected $bodyReceived = false;

    /** @var string */
    protected $requestEncodingType = null;

    /** @var string */
    protected $requestContentType = null;

    /** @var int */
    protected $requestBodySize = 0;

    /** @var int */
    protected $expectedChunkSize = 0;

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /** @var int */
    protected $cursorPositionInRequestPostBody = 0;

    /** @var bool */
    protected $formDataHeadersReceived = false;

    /** @var bool */
    protected $formDataReceived = false;

    /** @var mixed[] */
    protected $currentFormDataInfo = [];

    /** @var mixed[] */
    protected $requestFilesInfo = [];

    /** @var mixed[] */
    protected $serverHostCache = [];

    /** @var \php_user_filter[] */
    protected $streamFilters = [];

    protected $memoryStream = null;

    /**
     * @param callable $dispatcher
     * @param callable $errorHandler
     */
    public function __construct($dispatcher, $errorHandler = null)
    {
        HeadersFilter::register();
        BodyFilter::register();

        $this->errorHandler = $errorHandler;
        $this->chunkedHeader = new TransferEncoding(static::ENCODING_CHUNKED);
        $this->closeConnectionHeader = (new Connection())->setValue("close");
        $this->keepAliveConnectionHeader = (new Connection())->setValue("keep-alive; timeout=" . $this->keepAliveTimer);
        $this->dispatcher = $dispatcher;
        $this->useNewRequest();
        $this->restartKeepAliveCounter();
    }

    /**
     * @return callable
     */
    public function getErrorHandler()
    {
        return $this->errorHandler;
    }

    /**
     * @return callable
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this->restartKeepAliveCounter();
        $this->requestPhase = static::REQUEST_PHASE_KEEP_ALIVE;
    }

    public function onError(ConnectionInterface $connection, $exception)
    {
        if (!$this->request) {
            $this->request = new Request();
        }

        $callback = function($request, $response) use ($exception) {
            $errorHandler = $this->getErrorHandler();

            if (is_callable($errorHandler)) {
                $errorHandler($request, $response, $exception);
            } else {
                $this->setErrorResponse($exception->getCode() >= Response::STATUS_CODE_400 ? $exception->getCode() : Response::STATUS_CODE_500, $exception->getMessage());
            }
        };

        $this->requestComplete = true;
        $this->dispatchRequest($connection, $callback);

        if ($exception->getCode() === Response::STATUS_CODE_400) {
            $this->onClose($connection);
        }

        throw $exception;
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->requestPhase = static::REQUEST_PHASE_IDLE;
        $connection->end();
        $this->useNewRequest();
        $this->restartKeepAliveCounter();
    }

    public function onHeartBeat(ConnectionInterface $connection)
    {
        switch ($this->requestPhase) {
            case static::REQUEST_PHASE_KEEP_ALIVE:
                $this->keepAliveTimer--;

                if ($this->keepAliveTimer === 0) {
                    $connection->end();
                }
                break;

            default:
                break;
        }
    }

    public function onMessage(ConnectionInterface $connection, $message)
    {
        fwrite($this->memoryStream, $message);

        $this->requestPhase = static::REQUEST_PHASE_READING;

        if (!$this->headersReceived) {
            if (HeadersFilter::isFinished()) {
                $this->request = HeadersFilter::getRequest();
                try {
                    $this->validateRequestHeaders($connection);
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('Incorrect headers: ' . $e->getMessage(), Response::STATUS_CODE_400);
                }
                $this->headersReceived = true;
                $pos = ftell($this->memoryStream);
                rewind($this->memoryStream);

                $message = fread($this->memoryStream, $pos);
                var_dump($message);
                echo "TRUNCATING...$message $pos \n";
                //ftruncate($this->memoryStream, 0);
            }
        }

        if ($this->headersReceived) {
            BodyFilter::setRequest($this->request);
            BodyFilter::addToStream($this->memoryStream);
            echo "WRITING...\n";
            fwrite($this->memoryStream, $message);
            echo "WROTE\n";
            var_dump($message, BodyFilter::getRequest());

            $this->decodeRequestBody($connection, $message);

            if ($this->isBodyAllowedInRequest()) {
                $this->parseRequestPostData();
                $this->parseRequestFileData();
            }

            if ($this->bodyReceived && $this->headersReceived) {
                $this->requestComplete = true;
            }

            if ($this->requestComplete) {
                $callback = $this->getDispatcher();
                $this->dispatchRequest($connection, $callback);
            }
        }
    }

    protected function dispatchRequest(ConnectionInterface $connection, $callback)
    {
        $this->requestPhase = static::REQUEST_PHASE_PROCESSING;

        ob_start(function($buffer) use ($connection) { $this->sendResponse($connection, $buffer); }, $this->bufferSize);
        $this->mapUploadedFiles();
        $callback($this->request, $this->response);
        $this->requestPhase = static::REQUEST_PHASE_SENDING;
        ob_end_flush();
    }

    protected function useNewRequest()
    {
        $this->isChunkedResponse = false;
        $this->headersSent = false;
        $this->request = null;
        $this->response = new Response();
        $this->buffer = '';
        $this->body = '';
        $this->headersReceived = false;
        $this->bodyReceived = false;
        $this->requestComplete = false;
        $this->requestEncodingType = null;
        $this->requestContentType = null;
        $this->cursorPositionInRequestPostBody = 0;
        $this->formDataHeadersReceived = false;
        $this->formDataReceived = false;
        $this->currentFormDataInfo = [];
        $this->requestBodySize = 0;
        $this->expectedChunkSize = 0;
        $this->deleteTemporaryFiles();
        $this->requestFilesInfo = [];

        $fiveMBs = 5 * 1024 * 1024;
        //$this->memoryStream = fopen("php://temp/maxmemory:$fiveMBs", 'r+');
        $this->memoryStream = fopen("/tmp/test", 'w+');
        HeadersFilter::addToStream($this->memoryStream);
    }

    protected function setHost(ConnectionInterface $connection, $fullHost)
    {
        // URI host & port
        $host = null;
        $port = null;

        if (isset($this->serverHostCache[$fullHost])) {
            $currentUri = $this->request->getUri();
            $cachedUri = $this->serverHostCache[$fullHost];
            $currentUri->setHost($cachedUri['host']);
            $currentUri->setPort($cachedUri['port']);

            return;
        }

        // Set the host
        if ($fullHost) {
            // works for regname, IPv4 & IPv6
            if (preg_match('|\:(\d+)$|', $fullHost, $matches)) {
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
                //$fullHost = '';
            } else {
                $this->serverHostCache[$fullHost] = [
                    'host' => $host,
                    'port' => $port
                ];
            }
        }

        if (!$host) {
            $connectionServerAddress = $connection->getServerAddress();
            if (preg_match('|\:(\d+)$|', $connectionServerAddress, $matches)) {
                $host = substr($connectionServerAddress, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int)$matches[1];
                //$fullHost = $connectionServerAddress;
            }
        }

        //$_SERVER['HTTP_HOST'] = $fullHost;
        $uri = $this->request->getUri();
        $uri->setHost($host);
        $uri->setPort($port);
    }

    protected function validateRequestHeaders(ConnectionInterface $connection)
    {
        $host = $this->request->getHeaderOverview('Host');
        $this->setHost($connection, $host);

        // todo: validate hostname?
        if ($this->request->getVersion() === Request::VERSION_11) {
            if (!$host) {
                throw new \InvalidArgumentException("HTTP 1.1 requests must include the Host: header", Response::STATUS_CODE_400);
            }

            // everything's ok, should we send "100 Continue" first?
            $expectHeader = $this->request->getHeaderOverview('Expect');
            if ($expectHeader === '100-continue') {
                $connection->write(sprintf("HTTP/%s 100 Continue\r\n\r\n", $this->request->getVersion()));
            }
        }
    }

    protected function getRequestContentType()
    {
        if (!$this->requestContentType) {
            $this->requestContentType = $this->request->getHeaderOverview('Content-Type', true);
        }

        return $this->requestContentType;
    }

    /**
     * @return string
     */
    protected function getMultipartDataBoundary()
    {
        $contentType = $this->getRequestContentType();

        if (preg_match('~^multipart/form-data; boundary=([^\r\n]+)$~i', $contentType, $matches)) {
            return '--' . $matches[1];
        }

        // @todo: validate the above header
    }

    protected function parseRequestFileData()
    {
        $boundaryString = $this->getMultipartDataBoundary();

        if ($this->formDataReceived || !$boundaryString) {
            return;
        }

        $closingBoundaryString = $boundaryString . '--';

        while (false !== ($pos = strpos($this->body, "\r\n"))) {
            if (!$this->formDataHeadersReceived) {
                // initialize data block
                if (!isset($this->currentFormDataInfo['tmp_name'])) {
                    // file not opened yet, check the boundary
                    if (substr($this->body, 0, $pos) !== $boundaryString) {
                        throw new \InvalidArgumentException("Boundary missing in multipart data", Response::STATUS_CODE_400);
                    }

                    $tmpFileName = tempnam(sys_get_temp_dir(), 'php_upload_');
                    $file = fopen($tmpFileName, 'w+');
                    $tmpFileName = stream_get_meta_data($file)['uri'];
                    $this->currentFormDataInfo['handle'] = $file;
                    $this->currentFormDataInfo['tmp_name'] = $tmpFileName;
                    $this->currentFormDataInfo['type'] = 'text/plain';
                    $this->currentFormDataInfo['error'] = UPLOAD_ERR_OK;
                    $this->body = substr($this->body, $pos + 2);

                    continue;
                }

                // check headers
                // is this the last header?
                if ($pos === 0) {
                    $this->formDataHeadersReceived = true;
                    $this->body = substr($this->body, 2);

                    continue;
                }

                $headerLine = substr($this->body, 0, $pos);

                // check the header...
                $header = GenericHeader::fromString($headerLine);
                $headerFieldName = strtolower($header->getFieldName());
                switch ($headerFieldName) {
                    case 'content-type':
                        $this->currentFormDataInfo['type'] = $header->getFieldValue();
                        break;

                    case 'content-disposition':
                        $headerValue = $header->getFieldValue();
                        $headerParts = explode(';', $headerValue);

                        foreach ($headerParts as $key => $part) {
                            $part = trim($part);
                            if ($part === 'form-data') {
                                // ok
                            } else if (preg_match('~^filename="([^"]+)"$~i', $part, $matches)) {
                                $this->currentFormDataInfo['name'] = $matches[1];
                            } else if (preg_match('~^name="([^"]+)"$~i', $part, $matches)) {
                                $this->currentFormDataInfo['form_name'] = $matches[1];
                            } else{
                                throw new \InvalidArgumentException("Unknown content-disposition parameter: $part", Response::STATUS_CODE_400);
                            }
                        }
                        break;

                    default:
                        // @todo: validate other headers
                        break;
                }

                $this->body = substr($this->body, strlen($headerLine) + 2);
                continue;
            }

            if ($this->formDataHeadersReceived) {
                $bodyLine = substr($this->body, 0, $pos);
                // check if there's a boundary in the buffer
                if ($bodyLine === $boundaryString || $bodyLine === $closingBoundaryString) {
                    $this->registerUploadedFile();

                    if ($bodyLine === $closingBoundaryString) {
                        $this->formDataReceived = true;

                        return;
                    }

                    continue;
                }

                fwrite($this->currentFormDataInfo['handle'], $bodyLine);
                $this->body = substr($this->body, $pos + 2);

                continue;
            }
        }

        if (!$this->formDataHeadersReceived) {
            // headers are incomplete, fetch more data...
            return;
        } else {
            // check if there's a boundary in the buffer
            if ($this->body === $boundaryString . '--') {
                $this->registerUploadedFile();

                $this->body = '';

                $this->formDataReceived = true;
                // @todo: validate if ending boundary line is exactly at the end of a request
                return;
            }
        }

        // no new line found, check if its a buffer that can be sent to disk (or if it may contain part of the boundary)
        if ($this->body !== substr($boundaryString, 0, strlen($this->body)) && $this->body !== substr($closingBoundaryString, 0, strlen($this->body))) {
            if (substr($this->body, -1) === "\r") {
                // the new line at the end of a buffer may preceed the boundary string, don't write anything yet
                return;
            }
            fwrite($this->currentFormDataInfo['handle'], $this->body);
            $this->body = '';
            return;
        } else {
            // there may be a boundary hidden here, fetch more data
            return;
        }
    }

    protected function registerUploadedFile()
    {
        $this->formDataHeadersReceived = false;
        $this->currentFormDataInfo['size'] = filesize($this->currentFormDataInfo['tmp_name']);
        fclose($this->currentFormDataInfo['handle']);
        unset($this->currentFormDataInfo['handle']);

        if (!isset($this->currentFormDataInfo['name'])) {
            // its not a file, just a POST variable
            // for now, read it into memory
            // @todo: handle big data (stream from file to variable?)
            $this->request->getPost()->set($this->currentFormDataInfo['form_name'], file_get_contents($this->currentFormDataInfo['tmp_name']));
            unlink($this->currentFormDataInfo['tmp_name']);
            $this->currentFormDataInfo = [];

            return;
        }

        $this->requestFilesInfo[$this->currentFormDataInfo['form_name']][] = $this->currentFormDataInfo;
        $this->currentFormDataInfo = [];
    }

    protected function mapUploadedFiles()
    {
        $this->request->getFiles()->fromArray($this->requestFilesInfo);
    }

    protected function deleteTemporaryFiles()
    {
        foreach ($this->requestFilesInfo as $formData) {
            foreach ($formData as $file) {
                if (file_exists($file['tmp_name'])) {
                    unlink($file['tmp_name']);
                }
            }
        }
    }

    protected function parseRequestPostData()
    {
        if ($this->getRequestContentType() !== 'application/x-www-form-urlencoded') {
            return;
        }

        $requestPost = $this->request->getPost();

        while (false !== ($pos = strpos($this->body, "&", $this->cursorPositionInRequestPostBody)) || $this->bodyReceived) {
            $paramsLength = $pos === false ? strlen($this->body) : $pos;
            $postParameter = substr($this->body, $this->cursorPositionInRequestPostBody, $paramsLength - $this->cursorPositionInRequestPostBody);
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

    protected function decodeRegularRequestBody(& $message)
    {
        $expectedBodyLength = $this->request->getHeaderOverview('Content-Length');

        if (is_null($expectedBodyLength) || !ctype_digit($expectedBodyLength)) {
            throw new \InvalidArgumentException("Invalid or missing Content-Length header", Response::STATUS_CODE_400);
        }

        $expectedBodyLength = (int) $expectedBodyLength;
        $messageLength = strlen($message);

        if ($this->requestBodySize < $expectedBodyLength) {
            // check if message fits into the gap...
            if ($messageLength + $this->requestBodySize > $expectedBodyLength) {
                throw new \InvalidArgumentException("Request body is larger than set in the Content-Length header", Response::STATUS_CODE_400);
            }

            $this->body .= $message;
            $this->requestBodySize += $messageLength;
            $message = '';
        }

        if ($this->requestBodySize === $expectedBodyLength) {
            $this->request->setContent($this->body);
            $this->bodyReceived = true;
            $this->requestComplete = true;
        }
    }

    protected function decodeChunkedRequestBody(& $message)
    {
        $this->buffer .= $message;

        while (true) {
            if ($this->expectedChunkSize === -1) {
                // read the chunk header
                if (0 !== strpos($this->buffer, "\r\n")) {
                    // no chunk yet, read more data
                    return;
                }

                // @todo: support trailers!
                $this->requestComplete = true;
                $this->bodyReceived = true;
                $message = substr($message, 2);
                $this->request->setContent($this->body);

                return;
            }
            if ($this->expectedChunkSize === 0) {
                // read the chunk header
                if (false === strpos($this->buffer, "\r\n")) {
                    // no chunk yet, read more data
                    $message = '';
                    return;
                }

                if (!preg_match("/^([\da-fA-F]+)[^\r\n]*\r\n/", $this->buffer, $matches)) {
                    throw new \InvalidArgumentException("Invalid chunk header", Response::STATUS_CODE_400);
                }

                $headerSize = strpos($this->buffer, "\r\n") + 2;
                $chunkSize = hexdec($matches[1]);
                $this->buffer = substr($this->buffer, $headerSize);

                if ($chunkSize === 0) {
                    // that is a closing chunk, expect \r\n\r\n and stop parsing body
                    $this->expectedChunkSize = -1;
                    continue;
                }
                $this->expectedChunkSize = $chunkSize;
            }

            $bufferSize = strlen($this->buffer);

            // is chunk content complete?
            if ($bufferSize < $this->expectedChunkSize + 2) {
                // no, fetch more data
                $message = '';
                return;
            }

            $messageLeft = $bufferSize - $this->expectedChunkSize + 2;
            $this->body .= substr($this->buffer, 0, $this->expectedChunkSize);
            $this->buffer = substr($this->buffer, $this->expectedChunkSize + 2);
            $this->requestBodySize += $this->expectedChunkSize;

            if ($messageLeft < strlen($message)) {
                // chunk is part of the current message, so trim it
                $message = substr($message, -$messageLeft);
            } else {
                // body is already large enough that current chunk is not a part of current message, consume it
                // and continue
                $message = '';
            }

            $this->expectedChunkSize = 0;
        }
    }

    protected function decodeRequestBody(ConnectionInterface $from, & $message)
    {
        if ($this->bodyReceived || false === $this->headersReceived) {
            return;
        }

        if (!$this->isBodyAllowedInRequest()) {
            if (!isset($message[0])) {
                $this->requestComplete = true;
                $this->bodyReceived = true;
                return;
            }
            // method is not allowing to send a body
            throw new \InvalidArgumentException("Body not allowed in this request", Response::STATUS_CODE_400);
        }

        $encodingType = $this->getEncodingType();

        if ($encodingType === $this::ENCODING_CHUNKED) {
            $this->decodeChunkedRequestBody($message);
        } else {
            $this->decodeRegularRequestBody($message);
        }
    }

    protected function getEncodingType()
    {
        if (!$this->requestEncodingType) {
            if (!$this->request->getHeaderOverview('Transfer-Encoding')) {
                $this->requestEncodingType = $this::ENCODING_IDENTITY;
            } else {
                if ($this->request->getHeaderOverview('Transfer-Encoding', true) === $this::ENCODING_CHUNKED) {
                    $this->requestEncodingType = $this::ENCODING_CHUNKED;
                } else {
                    throw new \InvalidArgumentException("Not supported transfer encoding", Response::STATUS_CODE_501);
                }
            }
        }

        return $this->requestEncodingType;
    }

    protected function sendHeaders(ConnectionInterface $from, & $buffer)
    {
        $response = $this->response;
        $request = $this->request;
        $responseHeaders = $response->getHeaders();
        $requestVersion = $this->request->getVersion();
        $response->setVersion($requestVersion);

        $transferEncoding = $responseHeaders->get('Transfer-Encoding');
        $this->isChunkedResponse = ($transferEncoding && $transferEncoding->getFieldValue() === $this::ENCODING_CHUNKED);

        $connectionType = $request->getHeaderOverview('Connection', true);
        $connectionType = ($requestVersion === Request::VERSION_11 && $connectionType !== 'close') ? 'keep-alive' : $connectionType;

        $this->isKeepAliveConnection = $this->keepAliveCount > 0 && $connectionType === 'keep-alive';

        // keep-alive should be disabled for HTTP/1.0 and chunked output (btw. Transfer Encoding should not be set for 1.0)
        // we can also disable chunked response if buffer contained entire response body
        if ($requestVersion === Request::VERSION_10 || $this->requestPhase === static::REQUEST_PHASE_SENDING) {
            $this->isChunkedResponse = false;
            if ($transferEncoding) {
                $responseHeaders->removeHeader(new TransferEncoding());
            }

            $acceptEncoding = $request->getHeaderOverview('Accept-Encoding', true);
            $encodingsArray = $acceptEncoding ? explode(",", str_replace(' ', '', $acceptEncoding)) : [];
            if ($this->requestPhase === static::REQUEST_PHASE_SENDING && isset($buffer[8192]) && in_array('gzip', $encodingsArray)) {
                $buffer = substr(gzcompress($buffer, 1, ZLIB_ENCODING_GZIP), 0, -4);
                $responseHeaders->addHeader(new ContentEncoding('gzip'));
                $responseHeaders->addHeader(new Vary('Accept'));
            }
            $responseHeaders->addHeader(new ContentLength(strlen($buffer)));
        } else {
            if (!$this->isChunkedResponse && $this->isBodyAllowedInResponse() && !$responseHeaders->has('Content-Length')) {
                // is this a chunked encoding? valid only for HTTP 1.1+
                $responseHeaders->addHeader($this->chunkedHeader);

                $this->isChunkedResponse = true;
            }
        }

        $responseHeaders->addHeader($this->isKeepAliveConnection? $this->keepAliveConnectionHeader : $this->closeConnectionHeader);

        $from->write(
            $response->renderStatusLine() . "\r\n" .
            $responseHeaders->toString() .
            "Date: " . gmdate('D, d M Y H:i:s') . " GMT\r\n" .
            "\r\n");

        $this->headersSent = true;

        return $this;
    }

    protected function sendResponse(ConnectionInterface $from, $buffer)
    {
        if (!$this->headersSent) {
            $this->sendHeaders($from, $buffer);
        }

        if ($this->isBodyAllowedInResponse()) {
            if ($this->isChunkedResponse) {
                $bufferSize = strlen($buffer);
                if ($bufferSize > 0) {
                    $buffer = sprintf("%s\r\n%s\r\n", dechex($bufferSize), $buffer);
                }

                if ($this->requestPhase === static::REQUEST_PHASE_SENDING) {
                    $buffer .= "0\r\n\r\n";
                }
            }

            if ($buffer !== null) {
                $from->write($buffer);
            }
        }

        if ($this->requestPhase !== static::REQUEST_PHASE_SENDING) {
            return '';
        }

        if ($this->isKeepAliveConnection) {
            $this->keepAliveCount--;
            $this->useNewRequest();
            $this->restartKeepAliveTimer();
            $this->requestPhase = static::REQUEST_PHASE_KEEP_ALIVE;
        } else {
            $from->end();
            $this->useNewRequest();
            $this->restartKeepAliveCounter();
            $this->requestPhase = static::REQUEST_PHASE_IDLE;
        }

        return '';
    }

    /**
     * @param int $statusCode
     * @param string $message
     * @return Response
     */
    protected function setErrorResponse($statusCode, $message)
    {
        $this->response->setVersion($this->request->getVersion());

        // @todo use exception instead
        $this->response->setStatusCode($statusCode);
        echo $message;
    }

    /**
     * @return bool
     */
    protected function isBodyAllowedInRequest()
    {
        switch ($this->request->getMethod()) {
            case 'GET':
            case 'OPTIONS':
            case 'HEAD':
            case 'TRACE':
                return false;
            default:
                return true;
        }
    }

    /**
     * @return bool
     */
    protected function isBodyAllowedInResponse()
    {
        switch ($this->request->getMethod()) {
            case 'OPTIONS':
            case 'HEAD':
            case 'TRACE':
                return false;
            default:
                return true;
        }
    }

    protected function restartKeepAliveCounter()
    {
        $this->keepAliveCount = 100;
        $this->restartKeepAliveTimer();
    }

    protected function restartKeepAliveTimer()
    {
        $this->keepAliveTimer = 5;
    }
}