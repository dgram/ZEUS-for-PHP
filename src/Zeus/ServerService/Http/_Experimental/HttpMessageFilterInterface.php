<?php

namespace Zeus\ServerService\Http\Http\Message;

interface HttpMessageFilterInterface
{
    public static function register();

    public static function addToStream($stream);

    /**
     * @return mixed
     */
    public static function getRequest();

    /**
     * @param mixed $request
     */
    public static function setRequest($request);
}