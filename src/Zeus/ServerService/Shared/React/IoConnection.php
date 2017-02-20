<?php

namespace Zeus\ServerService\Shared\React;

use React\Socket\Connection;

/**
 * {@inheritdoc}
 */
class IoConnection extends Connection implements ConnectionInterface
{
    public function getServerAddress()
    {
        return stream_socket_get_name($this->stream, false);
    }
}
