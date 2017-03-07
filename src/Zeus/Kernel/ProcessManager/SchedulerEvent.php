<?php

namespace Zeus\Kernel\ProcessManager;
use Zend\EventManager\Event;

/**
 * @package Zeus\Kernel\ProcessManager
 */
class SchedulerEvent extends Event
{
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