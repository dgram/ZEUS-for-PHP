<?php

namespace Zeus\ServerService\Http\Http\Message;

use Zend\Http\Response;

class BodyFilter extends \php_user_filter implements HttpMessageFilterInterface
{
    protected static $request;

    public $stream;

    protected static $streamFilter;

    protected static $isFinished = false;

    public static function register()
    {
        stream_filter_register(static::class, static::class);
    }

    public static function addToStream($stream)
    {
        self::$request = null;
        self::$isFinished = false;
        self::$streamFilter = stream_filter_append($stream, static::class);
    }

    /**
     * @return mixed
     */
    public static function getRequest()
    {
        return static::$request;
    }

    /**
     * @param mixed $request
     */
    public static function setRequest($request)
    {
        static::$request = $request;
    }

    /**
     * @return bool
     */
    public static function isFinished()
    {
        return self::$isFinished;
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        $request = static::getRequest();

        while ($bucket = stream_bucket_make_writeable($in)) {
            $expectedBodyLength = $request->getHeaderOverview('Content-Length');

            if (is_null($expectedBodyLength) || !ctype_digit($expectedBodyLength)) {
                throw new \InvalidArgumentException("Invalid or missing Content-Length header", Response::STATUS_CODE_400);
            }

            $expectedBodyLength = (int) $expectedBodyLength;
            $messageLength = $bucket->datalen;
            $bucket->data = strtoupper($bucket->data);

            if ($consumed < $expectedBodyLength) {
                // check if message fits into the gap...
                if ($messageLength + $consumed > $expectedBodyLength) {
                    throw new \InvalidArgumentException("Request body is larger than set in the Content-Length header", Response::STATUS_CODE_400);
                }

                $request->setContent($request->getContent() . strtoupper($bucket->data));
            }

            $consumed += $messageLength;
        }

        return PSFS_PASS_ON;
    }
}