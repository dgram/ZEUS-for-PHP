<?php

namespace Zeus\Kernel\IpcServer\Adapter;

/**
 * Handles Inter Process Communication using SystemV functionality.
 */
class MsgAdapter implements IpcAdapterInterface
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

        $id = $this->getQueueId($namespace);

        if (!$id) {
            // something went wrong
            throw new \RuntimeException("Failed to find a queue for IPC");
        }

        $this->ipc = msg_get_queue($id, 0600);
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
        if (!@msg_send($this->getQueue(), 1, $message, true, true, $errno)) {
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
        $messageType = 1;
        msg_receive($this->getQueue(), $messageType, $messageType, self::MAX_MESSAGE_SIZE, $message, true, MSG_IPC_NOWAIT);

        return $message;
    }

    /**
     * Receives all messages from the queue.
     *
     * @return mixed[] Received messages.
     */
    public function receiveAll()
    {
        $messages = [];

        // early elimination
        $stats = msg_stat_queue($this->getQueue());
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
     * @return $this
     */
    public function disconnect($channelNumber = -1)
    {
        //msg_remove_queue($this->getQueue());

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
        return $this;
    }
}