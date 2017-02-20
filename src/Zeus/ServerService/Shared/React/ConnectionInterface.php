<?php

namespace Zeus\ServerService\Shared\React;

interface ConnectionInterface extends \React\Socket\ConnectionInterface
{
    public function getServerAddress();
}