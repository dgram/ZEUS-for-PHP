<?php

namespace Zeus\Kernel\ProcessManager\Scheduler;

interface EventsInterface
{
    const ON_PROCESS_CREATE = 'onProcessCreate';
    const ON_PROCESS_CREATED = 'onProcessCreated';

    const ON_PROCESS_MESSAGE = 'onProcessMessage';

    const ON_PROCESS_INIT = 'onProcessStarted';
    const ON_PROCESS_TERMINATED = 'onProcessTerminated';
    const ON_PROCESS_TERMINATE = 'onProcessTerminate';
    const ON_PROCESS_EXIT = 'onProcessExit';

    const ON_PROCESS_LOOP = 'onProcessLoop';

    const ON_PROCESS_RUNNING = 'onProcessRunning';
    const ON_PROCESS_IDLING = 'onProcessIdling';

    const ON_SCHEDULER_START = 'onSchedulerStart';
    const ON_SCHEDULER_STOP = 'onSchedulerStop';
    const ON_SCHEDULER_LOOP = 'onSchedulerLoop';
    const ON_SERVER_START = 'onServerStart';
    const ON_SERVER_STOP = 'onServerStop';
}