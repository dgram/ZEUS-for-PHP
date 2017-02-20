<?php

namespace Zeus\ServerService\Http;

use Zeus\Kernel\ProcessManager\Config as TaskManagerConfig;

class Config implements HttpConfigInterface
{
    /** @var int */
    private $keepAliveTimeout = 5;

    /** @var int */
    private $maxKeepAliveRequestsLimit = 100;

    /** @var bool */
    private $keepAliveEnabled = true;

    /** @var int */
    private $listenPort = 0;

    /** @var string */
    private $listenAddress = '';

    /**
     * Config constructor.
     * @param mixed[] $settings
     */
    public function __construct($settings = null)
    {
        if (isset($settings['listen_port'])) {
            $this->setListenPort($settings['listen_port']);
        }

        if (isset($settings['listen_address'])) {
            $this->setListenAddress($settings['listen_address']);
        }

        if (isset($settings['keep_alive_enabled'])) {
            $this->setKeepAliveEnabled($settings['keep_alive_enabled']);
        }

        if (isset($settings['keep_alive_timeout'])) {
            $this->setKeepAliveTimeout($settings['keep_alive_timeout']);
        }

        if (isset($settings['max_keep_alive_requests_limit'])) {
            $this->setKeepAliveTimeout($settings['max_keep_alive_requests_limit']);
        }
    }

    /**
     * @return int
     */
    public function getListenPort()
    {
        return $this->listenPort;
    }

    /**
     * @param int $listenPort
     * @return Config
     */
    public function setListenPort($listenPort)
    {
        $this->listenPort = $listenPort;

        return $this;
    }

    /**
     * @return string
     */
    public function getListenAddress()
    {
        return $this->listenAddress;
    }

    /**
     * @param string $listenAddress
     * @return Config
     */
    public function setListenAddress($listenAddress)
    {
        $this->listenAddress = $listenAddress;

        return $this;
    }

    /**
     * @return int
     */
    public function getKeepAliveTimeout()
    {
        return $this->keepAliveTimeout;
    }

    /**
     * @param int $keepAliveTimeout
     * @return Config
     */
    public function setKeepAliveTimeout($keepAliveTimeout)
    {
        $this->keepAliveTimeout = $keepAliveTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxKeepAliveRequestsLimit()
    {
        return $this->maxKeepAliveRequestsLimit;
    }

    /**
     * @param int $maxKeepAliveRequestsLimit
     * @return Config
     */
    public function setMaxKeepAliveRequestsLimit($maxKeepAliveRequestsLimit)
    {
        $this->maxKeepAliveRequestsLimit = $maxKeepAliveRequestsLimit;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isKeepAliveEnabled()
    {
        return $this->keepAliveEnabled;
    }

    /**
     * @param boolean $keepAliveEnabled
     * @return Config
     */
    public function setKeepAliveEnabled($keepAliveEnabled)
    {
        $this->keepAliveEnabled = $keepAliveEnabled;

        return $this;
    }

}