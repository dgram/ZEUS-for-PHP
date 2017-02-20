<?php

namespace Zeus\Kernel\ProcessManager\Exception;

class ProcessManagerException extends \RuntimeException
{
    const SERVER_CREATION_FAILURE = 1;
    const LOCK_FILE_ERROR = 2;
    const SERVER_NOT_RUNNING = 4;
    const INVALID_CONFIGURATION = 8;
    const SERVER_TERMINATED = 16;
    //const ALL_PROCESSES_EXITED_PREMATURELY = 32;
    const PROCESS_NOT_CREATED = 64;
    const CLI_MODE_REQUIRED = 128;
}