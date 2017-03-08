<?php

namespace Zeus\Kernel\ProcessManager;
use Zend\EventManager\Event;

/**
 * @package Zeus\Kernel\ProcessManager
 */
class SchedulerEvent extends Event
{
<<<<<<< HEAD
    const EVENT_PROCESS_CREATE = 'processCreate';
    const EVENT_PROCESS_CREATED = 'processCreated';

    const EVENT_PROCESS_MESSAGE = 'processMessage';

    const EVENT_PROCESS_INIT = 'processStarted';
    const EVENT_PROCESS_TERMINATED = 'processTerminated';
    const EVENT_PROCESS_TERMINATE = 'processTerminate';
    const EVENT_PROCESS_EXIT = 'processExit';

    const EVENT_PROCESS_LOOP = 'processLoop';

    const EVENT_PROCESS_RUNNING = 'processRunning';
    const EVENT_PROCESS_WAITING = 'processWaiting';

    const EVENT_SCHEDULER_START = 'schedulerStart';
    const EVENT_SCHEDULER_STOP = 'schedulerStop';
    const EVENT_SCHEDULER_LOOP = 'schedulerLoop';

    // WARNING: the following INTERNAL_* events should not be used in custom projects
    // and if used - are subjects to change and BC breaks.
    const INTERNAL_EVENT_KERNEL_START = 'serverStart';
    const INTERNAL_EVENT_KERNEL_STOP = 'serverStop';
=======
    const PROCESS_CREATE = 'processCreate';
    const PROCESS_CREATED = 'processCreated';

    const PROCESS_MESSAGE = 'processMessage';

    const PROCESS_INIT = 'processStarted';
    const PROCESS_TERMINATED = 'processTerminated';
    const PROCESS_TERMINATE = 'processTerminate';
    const PROCESS_EXIT = 'processExit';

    const PROCESS_LOOP = 'processLoop';

    const PROCESS_RUNNING = 'processRunning';
    const PROCESS_WAITING = 'processWaiting';

    const SCHEDULER_START = 'schedulerStart';
    const SCHEDULER_STOP = 'schedulerStop';
    const SCHEDULER_LOOP = 'schedulerLoop';
    const SERVER_START = 'serverStart';
    const SERVER_STOP = 'serverStop';
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26

    /** @var Scheduler */
    protected $scheduler;

    /** @var Process */
    protected $process;

    /**
     * @return Scheduler
     */
    public function getScheduler()
    {
        return $this->scheduler;
    }

    /**
     * @param Scheduler $scheduler
     * @return $this
     */
    public function setScheduler(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
        $this->setParam('scheduler', $scheduler);

        return $this;
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param Process $process
     * @return $this
     */
    public function setProcess(Process $process)
    {
        $this->process = $process;
        $this->setParam('process', $process);

        return $this;
    }
}