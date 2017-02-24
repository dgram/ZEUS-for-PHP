<?php

namespace Zeus\Kernel\IpcServer\Adapter;

/**
 * Handles Inter Process Communication using sockets functionality.
 *
 * @internal
 */
final class SocketAdapter implements IpcAdapterInterface
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

        $domain = strtoupper(substr(PHP_OS, 0, 3) == 'WIN' ? AF_INET : AF_UNIX);

        if (!socket_create_pair($domain, SOCK_SEQPACKET, 0, $this->ipc)) {
            $errorCode = socket_last_error();
            throw new \RuntimeException("Could not create IPC socket: " . socket_strerror($errorCode), $errorCode);
        }

        socket_set_nonblock($this->ipc[0]);
        socket_set_nonblock($this->ipc[1]);
    }

    /**
     * @param int $channelNumber
     * @return $this
     */
    public function useChannelNumber($channelNumber)
    {
        if ($channelNumber === 0 || $channelNumber === 1) {
            $this->channelNumber = $channelNumber;

            return $this;
        }

        throw new \LogicException("Invalid channel number");
    }

    /**
     * Sends a message to the queue.
     *
     * @param string $message
     * @return $this
     */
    public function send($message)
    {
        $message = base64_encode(serialize($message));

        socket_set_block($this->ipc[$this->channelNumber]);
        socket_write($this->ipc[$this->channelNumber], $message . "\n", strlen($message) + 1);
        socket_set_nonblock($this->ipc[$this->channelNumber]);

        return $this;
    }

    /**
     * Receives a message from the queue.
     *
     * @return mixed Received message.
     */
    public function receive()
    {
        $message = '';

        $readSocket = [$this->ipc[$this->channelNumber]];
        $writeSocket = $except = [];

        if ($value = @socket_select($readSocket, $writeSocket, $except, 0, 100)) {
            if ('stream' === get_resource_type($readSocket[0])) {
                // HHVM...
                $message = stream_get_line($readSocket[0], 165536);
            } else {
                socket_recv($readSocket[0], $message, 165536, MSG_DONTWAIT);
            }

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
        $readSocket = [$this->ipc[$this->channelNumber]];
        $writeSocket = $except = [];
        $messages = [];

        if (@socket_select($readSocket, $writeSocket, $except, 1)) {
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
        if ($channelNumber >= 0 && isset($this->ipc[$channelNumber])) {
            $socket = $this->ipc[$channelNumber];
            socket_shutdown($socket, 2);
            socket_close($socket);
            unset($this->ipc[$channelNumber]);

            return $this;
        }

        foreach ($this->ipc as $channelNumber => $socket) {
            socket_shutdown($socket, 2);
            socket_close($socket);
            unset($this->ipc[$channelNumber]);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public static function isSupported()
    {
        return function_exists('socket_create_pair');
    }
}