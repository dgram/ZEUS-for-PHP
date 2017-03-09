<?php

namespace Zeus\ServerService\Http\Message;

use Zend\Stdlib\RequestInterface;
use Zend\Http\Request as ZendRequest;

class RequestWrapper implements RequestInterface
{
    /** @var Request */
    protected $request;

    protected $headersOverview;

    public function getHeaderOverview($name, $toLower = false)
    {
        if (!$this->headersOverview) {
            $headers = $this->request->getHeaders()->toString();
            if (preg_match_all('/(?P<name>[^()><@,;:\"\\/\[\]?=}{ \t]+):[\s]*(?P<value>[^\r\n]*)' . "[\s]*\r\n/sS", $headers, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->headersOverview[strtolower($match['name'])][] = $match['value'];
                }
            }
        }
        $name = strtolower($name);
        if (!isset($this->headersOverview[$name])) {
            return null;
        }

        if (isset($this->headersOverview[$name][1])) {
            return $toLower ? strtolower($this->headersOverview[$name]) : $this->headersOverview[$name];
        }

        return $toLower ? strtolower($this->headersOverview[$name][0]) : $this->headersOverview[$name][0];
    }

    /**
     * @return string "keep-alive" or "close"
     */
    public function getConnectionType()
    {
        $connectionType = $this->getHeaderOverview('Connection', true);

        return ($this->request->getVersion() === ZendRequest::VERSION_11 && $connectionType !== 'close') ? 'keep-alive' : $connectionType;
    }

    public function __call($name, array $args)
    {
        return call_user_func_array([$this->request, $name], $args);
    }

    /**
     * RequestWrapper constructor.
     * @param RequestInterface $realRequest
     */
    public function __construct(RequestInterface $realRequest)
    {
        $this->request = $realRequest;
    }

    /**
     * Set metadata
     *
     * @param  string|int|array|\Traversable $spec
     * @param  mixed $value
     */
    public function setMetadata($spec, $value = null)
    {
        return $this->request->setMetadata($spec, $value);
    }

    /**
     * Get metadata
     *
     * @param  null|string|int $key
     * @return mixed
     */
    public function getMetadata($key = null)
    {
        return $this->request->getMetadata($key);
    }

    /**
     * Set content
     *
     * @param  mixed $content
     * @return mixed
     */
    public function setContent($content)
    {
        return $this->request->setContent($content);
    }

    /**
     * Get content
     *
     * @return mixed
     */
    public function getContent()
    {
        return $this->request->getContent();
    }
}