<?php

namespace Zeus\Kernel\IpcServer\Adapter;

/**
 * Handles Inter Process Communication using SystemV functionality.
 *
 * @internal
 */
final class MsgAdapter implements IpcAdapterInterface
{
    const MAX_MESSAGE_SIZE = 16384;

    /**
     * Queue link.
     *
     * @var resource
     */
    protected $ipc;

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

        $id1 = $this->getQueueId($namespace);
        $this->ipc[0] = msg_get_queue($id1, 0600);
        $id2 = $this->getQueueId($namespace);
        $this->ipc[1] = msg_get_queue($id2, 0600);

        if (!$id1 || !$id2) {
            // something went wrong
            throw new \RuntimeException("Failed to find a queue for IPC");
        }
    }

    /**
     * @todo: handle situation where all queues are reserved already
     * @param string $channelName
     * @return int|bool
     */
    protected function getQueueId($channelName)
    {
        $id = 0;

        while (msg_queue_exists($id)) {
            $id++;
        }

        return $id;
    }

    /**
     * @return resource
     */
    protected function getQueue()
    {
        return $this->ipc;
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

        if (strlen($message) > static::MAX_MESSAGE_SIZE) {
            throw new \RuntimeException("Message lengths exceeds max packet size of " . static::MAX_MESSAGE_SIZE);
        }

        if (!@msg_send($this->ipc[$channelNumber], 1, $message, true, true, $errno)) {
            // @todo: handle this case
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


        $messageType = 1;
        msg_receive($this->ipc[$channelNumber], $messageType, $messageType, self::MAX_MESSAGE_SIZE, $message, true, MSG_IPC_NOWAIT);

        return $message;
    }

    /**
     * Receives all messages from the queue.
     *
     * @return mixed[] Received messages.
     */
    public function receiveAll()
    {
        $channelNumber = $this->channelNumber;

        $messages = [];

        // early elimination
        $stats = msg_stat_queue($this->ipc[$channelNumber]);
        if (!$stats['msg_qnum']) {

            // nothing to read
            return $messages;
        }

        for(;;) {
            $message = $this->receive();

            if (!$message) {
                break;
            }

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Destroys this IPC object.
     *
     * @param int $channelNumber
     * @return $this
     */
    public function disconnect($channelNumber = -1)
    {
        if ($channelNumber !== -1) {
            return $this;
        }

        foreach ($this->ipc as $channelNumber => $stream) {
            msg_remove_queue($this->ipc[$channelNumber]);
            unset($this->ipc[$channelNumber]);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public static function isSupported()
    {
        return function_exists('msg_stat_queue');
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