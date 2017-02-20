<?php

namespace Zeus\Kernel\IpcServer;

use Zend\Log\Logger;

/**
 * Server message.
 */
class Message
{
    /**
     * This is a debug message.
     */
    const IS_DEBUG = Logger::DEBUG;

    /**
     * This is a standard message.
     */
    const IS_MESSAGE = Logger::INFO;

    /**
     * This is an error message.
     */
    const IS_ERROR = Logger::ERR;

    /**
     * This is a status message.
     */
    const IS_STATUS = 1000;

    /**
     * This is a server status message.
     */
    const IS_SERVER_STATUS = 1500;

    /** @var mixed[] */
    protected $message;

    /** @var int */
    protected $type;

    /** @var int */
    protected $time;

    /** @var int */
    protected $id;

    /** @var mixed[] */
    protected $details = [];

    /**
     * Creates new server message.
     *
     * @param int $type Message type.
     * @param string|mixed $message Message content.
     * @param mixed[] $extra Extra details
     * @return Message
     */
    public function __construct($type, $message, $extra = [])
    {
        $this->type = $type;
        $this->message = $message;
        $this->time = time();
        if (!isset($extra['uid'])) {
            $extra['uid'] = getmypid();
        }

        $this->details = $extra;
    }

    /**
     * @return \mixed[]
     */
    public function getOptions()
    {
        return $this->details;
    }

    /**
     * @param \mixed[] $details
     * @return Message
     */
    public function setDetails($details)
    {
        $this->details = $details;

        return $this;
    }

    /**
     * @return string|mixed
     */
    public function getPayload()
    {
        return $this->message;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int|null
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return gmdate(DATE_RFC822, $this->getTime());
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}