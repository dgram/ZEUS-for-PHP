<?php

namespace Zeus\Kernel\IpcServer\Adapter;

/**
 * Class FifoAdapter
 * @package Zeus\Kernel\IpcServer\Adapter
 * @internal
 */
class FifoAdapter implements IpcAdapterInterface
{
    /** @var resource[] sockets */
    protected $ipc = [];

    /** @var string */
    protected $namespace;

    /** @var int */
    protected $channelNumber = 0;

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

        $fileName1 = $this->getFilename(0);
        $fileName2 = $this->getFilename(1);
        posix_mkfifo($fileName1, 0600);
        posix_mkfifo($fileName2, 0600);

        $this->ipc[0] = fopen($fileName1, "r+"); // ensures at least one writer (us) so will be non-blocking
        $this->ipc[1] = fopen($fileName2, "r+"); // ensures at least one writer (us) so will be non-blocking
        stream_set_blocking($this->ipc[0], false); // prevent fread / fwrite blocking
        stream_set_blocking($this->ipc[1], false); // prevent fread / fwrite blocking
    }

    protected function getFilename($channelNumber)
    {
        return sprintf("%s/%s.%d", getcwd(), $this->namespace, $channelNumber);
    }

    /**
     * @param int $channelNumber
     * @return $this
     */
    public function useChannelNumber($channelNumber)
    {
        $this->channelNumber = $channelNumber;
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
        $message = base64_encode(serialize($message));

        stream_set_blocking($this->ipc[$channelNumber], true);
        fwrite($this->ipc[$channelNumber], $message . "\n", strlen($message) + 1);
        stream_set_blocking($this->ipc[$channelNumber], false);

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

        if ($channelNumber == 0) {
            $channelNumber = 1;
        } else {
            $channelNumber = 0;
        }

        $readSocket = [$this->ipc[$channelNumber]];
        $writeSocket = $except = [];

        if ($value = @stream_select($readSocket, $writeSocket, $except, 0, 100)) {
            $message = fgets($readSocket[0], 165536);

            if (is_string($message) && $message !== "") {
                $message = unserialize(base64_decode($message));
                return $message;
            }
        }
    }

    /**
     * Receives all messages from the queue.
     *
     * @return mixed[] Received messages.
     */
    public function receiveAll()
    {
        $channelNumber = $this->channelNumber;

        if ($channelNumber == 0) {
            $channelNumber = 1;
        } else {
            $channelNumber = 0;
        }

        $readSocket = [$this->ipc[$channelNumber]];
        $writeSocket = $except = [];
        $messages = [];

        if (@stream_select($readSocket, $writeSocket, $except, 1)) {
            for (;;) {
                $message = $this->receive();
                if ($message === null) {

                    break;
                }

                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param int $channelNumber
     * @return $this
     */
    public function disconnect($channelNumber = -1)
    {
        if ($channelNumber !== -1) {
            return $this;
        }

        foreach ($this->ipc as $channelNumber => $stream) {
            fclose($this->ipc[$channelNumber]);
            unlink($this->getFilename($channelNumber));
            unset($this->ipc[$channelNumber]);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public static function isSupported()
    {
        return function_exists('posix_mkfifo');
    }
}