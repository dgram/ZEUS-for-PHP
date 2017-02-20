<?php

namespace Zeus\ServerService\Shared\React;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

interface IoServerInterface
{
    /**
     * IoServer constructor.
     * @param MessageComponentInterface $app
     * @param ServerInterface $socket
     * @param LoopInterface|null $loop
     */
    public function __construct(MessageComponentInterface $app, ServerInterface $socket, LoopInterface $loop = null);

    /**
     * @return ServerInterface
     */
    public function getServer();
}