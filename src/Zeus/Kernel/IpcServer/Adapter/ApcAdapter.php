<?php

namespace Zeus\Kernel\IpcServer\Adapter;

/**
 * Handles Inter Process Communication using APCu functionality.
 * @internal
 */
final class ApcAdapter implements IpcAdapterInterface
{
    /** @var string */
    protected $namespace;

    /** @var mixed[] */
    protected $config;

    /** @var int */
    protected $channelNumber = 0;

    /**
     * Creates IPC object.
     *
     * @param string $namespace
     * @param mixed[] $config
     */
    public function __construct($namespace, array $config)
    {
        $this->namespace = $namespace;
        $this->config = $config;

        if (static::isSupported()) {
            apcu_store($this->namespace . '_readindex_0', 0, 0);
            apcu_store($this->namespace . '_writeindex_0', 1, 0);
            apcu_store($this->namespace . '_readindex_1', 0, 0);
            apcu_store($this->namespace . '_writeindex_1', 1, 0);
        }
    }

    /**
     * Sends a message to the queue.
     *
     * @param string $message
     * @return $this
     */
    public function send($message)
    {
        $channelNumber = $this->channelNumber;

        if ($channelNumber == 0) {
            $channelNumber = 1;
        } else {
            $channelNumber = 0;
        }

        $index = apcu_fetch($this->namespace . '_writeindex_' . $channelNumber);
        apcu_store($this->namespace . '_data_' . $channelNumber . '_' . $index, $message, 0);

        if (65535 < apcu_inc($this->namespace . '_writeindex_' . $channelNumber)) {
            apcu_store($this->namespace . '_writeindex_' . $channelNumber, 1, 0);
        }

        return $this;
    }

    /**
     * Receives a message from the queue.
     *
     * @return mixed Received message.
     */
    public function receive()
    {
        $channelNumber = $this->channelNumber;

        $readIndex = apcu_fetch($this->namespace . '_readindex_' . $channelNumber);
        $result = apcu_fetch($this->namespace . '_data_' . $channelNumber . '_' . $readIndex);
        apcu_delete($this->namespace . '_data_' . $channelNumber . '_' . $readIndex);

        if (65535 < apcu_inc($this->namespace . '_readindex_' . $channelNumber)) {
            apcu_store($this->namespace . '_readindex_' . $channelNumber, 0, 0);
        }

        return $result;
    }

    /**
     * Receives all messages from the queue.
     *
     * @return mixed[] Received messages.
     */
    public function receiveAll()
    {
        $results = [];
        while ($result = $this->receive()) {
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Destroys this IPC object.
     *
     * @param int $channelNumber
     * @return $this
     */
    public function disconnect($channelNumber = 0)
    {
        return $this;
    }

    /**
     * @return bool
     */
    public static function isSupported()
    {
        return (
            extension_loaded('apcu')
            &&
            false !== @apcu_cache_info()
            &&
            function_exists('apcu_store')
            &&
            function_exists('apcu_fetch')
        );
    }

    /**
     * @param int $channelNumber
     * @return $this
     */
    public function useChannelNumber($channelNumber)
    {
        $this->channelNumber = $channelNumber;

        return $this;
    }
}