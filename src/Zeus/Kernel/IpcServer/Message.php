<?php

namespace Zeus\Kernel\IpcServer;

/**
 * Server message.
 *
 * @internal
 */
final class Message
{
    /**
     * This is a status message.
     */
    const IS_STATUS = 1000;
    const IS_STATUS_REQUEST = 1001;
}