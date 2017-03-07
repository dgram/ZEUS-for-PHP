<?php

namespace Zeus\Kernel\ProcessManager;

/**
 * Interface EventsInterface
 * @package Zeus\Kernel\ProcessManager
 * @deprecated
 * @internal
 *
 * This interface is deprecated and will be removed in next version, do not use it
 */
interface EventsInterface
{
    const ON_PROCESS_CREATE = 'processCreate';
    const ON_PROCESS_CREATED = 'processCreated';

    const ON_PROCESS_MESSAGE = 'processMessage';

    const ON_PROCESS_INIT = 'processStarted';
    const ON_PROCESS_TERMINATED = 'processTerminated';
    const ON_PROCESS_TERMINATE = 'processTerminate';
    const ON_PROCESS_EXIT = 'processExit';

    const ON_PROCESS_LOOP = 'processLoop';

    const ON_PROCESS_RUNNING = 'processRunning';
    const ON_PROCESS_WAITING = 'processWaiting';

    const ON_SCHEDULER_START = 'schedulerStart';
    const ON_SCHEDULER_STOP = 'schedulerStop';
    const ON_SCHEDULER_LOOP = 'schedulerLoop';
    const ON_SERVER_START = 'serverStart';
    const ON_SERVER_STOP = 'serverStop';
}