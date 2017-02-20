<?php

namespace Zeus\ServerService\Shared\React;

interface HeartBeatMessageInterface
{
    public function onHeartBeat(ConnectionInterface $connection, $data = null);
}