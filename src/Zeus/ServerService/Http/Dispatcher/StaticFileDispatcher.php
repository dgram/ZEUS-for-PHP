<?php

namespace Zeus\ServerService\Http\Dispatcher;

use Zend\Http\Header\ContentLength;
use Zend\Http\Header\ContentType;
use Zend\Http\Request;
use Zend\Http\Response;
use Zeus\ServerService\Http\MimeType;

class StaticFileDispatcher implements DispatcherInterface
{
    /** @var DispatcherInterface */
    protected $anotherDispatcher;

    /** @var mixed[] */
    protected $config;

    /**
     * StaticFileDispatcher constructor.
     * @param mixed[] $config
     * @param DispatcherInterface|null $anotherDispatcher
     */
    public function __construct(array $config, DispatcherInterface $anotherDispatcher = null)
    {
        $this->config = $config;
        $this->anotherDispatcher = $anotherDispatcher;
    }

    /**
     * @param Request $httpRequest
     * @return Response
     */
    public function dispatch(Request $httpRequest)
    {
        $path = $httpRequest->getUri()->getPath();

        $code = Response::STATUS_CODE_200;

        $publicDirectory = isset($this->config['public_directory']) ? $this->config['public_directory'] : getcwd() . '/public';
        $fileName = $publicDirectory . $path;
        $realPath = substr(realpath($fileName), 0, strlen($publicDirectory));
        if ($realPath && $realPath !== $publicDirectory) {
            $code = Response::STATUS_CODE_400;
        }

        $blockedFileTypes = isset($this->config['blocked_file_types']) ? implode('|', $this->config['blocked_file_types']) : null;

        if (file_exists($fileName) && !is_dir($fileName)) {
            if ($blockedFileTypes && preg_match('~\.(' . $blockedFileTypes . ')$~', $fileName)) {
                $code = Response::STATUS_CODE_403;
            } else {
                $httpResponse = $this->getHttpResponse($code, $httpRequest->getVersion());
                $httpResponse->getHeaders()->addHeader(new ContentLength(filesize($fileName)));
                $httpResponse->getHeaders()->addHeader(new ContentType(MimeType::getMimeType($fileName)));
                readfile($fileName);

                return $httpResponse;
            }
        } else {
            $code = is_dir($fileName) ? Response::STATUS_CODE_403 : Response::STATUS_CODE_404;

            if ($this->anotherDispatcher) {
                return $this->anotherDispatcher->dispatch($httpRequest);
            }
        }

        return $this->getHttpResponse($code, $httpRequest->getVersion());
    }

    /**
     * @param int $code
     * @param string $version
     * @return Response
     */
    protected function getHttpResponse($code, $version)
    {
        $httpResponse = new Response();
        $httpResponse->setStatusCode($code);
        $httpResponse->setVersion($version);

        return $httpResponse;
    }
}