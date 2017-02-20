<?php

namespace Zeus\ServerService\Http;

interface HttpConfigInterface
{
    /**
     * @return int
     */
    public function getKeepAliveTimeout();

    /**
     * @param int $keepAliveTimeout
     * @return HttpConfigInterface
     */
    public function setKeepAliveTimeout($keepAliveTimeout);

    /**
     * @return int
     */
    public function getMaxKeepAliveRequestsLimit();

    /**
     * @param int $keepAliveRequests
     * @return HttpConfigInterface
     */
    public function setMaxKeepAliveRequestsLimit($keepAliveRequests);

    /**
     * @return boolean
     */
    public function isKeepAliveEnabled();

    /**
     * @param boolean $keepAliveEnabled
     * @return HttpConfigInterface
     */
    public function setKeepAliveEnabled($keepAliveEnabled);

}