<?php

namespace Zeus\ServerService\Http\Http\Message;

class HeadersFilter extends \php_user_filter implements HttpMessageFilterInterface
{
    protected static $request;

    public $stream;

    protected static $streamFilter;

    public static function register()
    {
        stream_filter_register(static::class, static::class);
    }

    public static function addToStream($stream)
    {
        self::$request = null;
        self::$streamFilter = stream_filter_append($stream, static::class);
    }

    /**
     * @return mixed
     */
    public static function getRequest()
    {
        return self::$request;
    }

    /**
     * @param mixed $request
     */
    public static function setRequest($request)
    {
        self::$request = $request;
    }

    /**
     * @return bool
     */
    public static function isFinished()
    {
        return is_object(self::$request);
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        if (static::isFinished()) {
            return PSFS_PASS_ON;
        }

        while ($bucket = stream_bucket_make_writeable($in)) {
            $data = $bucket->data;
            $eol = strpos($data, "\r\n\r\n");
            if ($eol === false) {
                return PSFS_FEED_ME;
            }

            $headers = substr($bucket->data, 0, $eol + 4);

            $consumed += $eol + 4;
            $bucket->data = substr($bucket->data, $consumed);
            $bucket->datalen = $bucket->datalen - ($eol + 4);
            stream_bucket_append($out, $bucket);
            static::setRequest(Request::fromStringOfHeaders($headers));

            //stream_filter_remove(self::$streamFilter);

            break;
        }

        return PSFS_PASS_ON;
    }
}