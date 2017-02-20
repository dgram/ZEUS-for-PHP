<?php

namespace Zeus\Kernel\ProcessManager\MultiProcessingModule;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zeus\Kernel\ProcessManager\Exception\ProcessManagerException;
use Zeus\Kernel\ProcessManager\Scheduler\EventsInterface;

final class PosixProcess implements MultiProcessingModuleInterface
{
    /** @var EventManagerInterface */
    protected $events;

    /** @var int Parent PID */
    public $ppid;

    /**
     * PosixDriver constructor.
     */
    public function __construct()
    {
        $this->checkSetup();
        $this->ppid = getmypid();
    }

    /**
     * @param EventManagerInterface $events
     * @return $this
     */
    public function attach(EventManagerInterface $events)
    {
        $events->attach(EventsInterface::ON_PROCESS_CREATE, [$this, 'startTask']);
        $events->attach(EventsInterface::ON_PROCESS_IDLING, [$this, 'sigUnblock']);
        $events->attach(EventsInterface::ON_PROCESS_TERMINATE, [$this, 'onProcessTerminate']);
        $events->attach(EventsInterface::ON_PROCESS_LOOP, [$this, 'sigDispatch']);
        $events->attach(EventsInterface::ON_PROCESS_RUNNING, [$this, 'sigBlock']);
        $events->attach(EventsInterface::ON_SERVER_START, [$this, 'onServerInit']);
        $events->attach(EventsInterface::ON_SCHEDULER_START, [$this, 'onSchedulerInit']);
        $events->attach(EventsInterface::ON_SCHEDULER_STOP, [$this, 'shutdownServer']);
        $events->attach(EventsInterface::ON_SCHEDULER_LOOP, [$this, 'processSignals']);

        $this->events = $events;

        return $this;
    }

    /**
     * @return $this
     */
    private function checkSetup()
    {
        $className = basename(str_replace('\\', '/', get_class($this)));

        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException(sprintf("PCNTL extension is required by %s but disabled in PHP",
                    $className
                )
            );
        }

        $requiredFunctions = [
            'pcntl_signal',
            'pcntl_sigprocmask',
            'pcntl_signal_dispatch',
            'pcntl_wifexited',
            'pcntl_wait',
            'posix_getppid',
            'posix_kill'
        ];

        $missingFunctions = [];

        foreach ($requiredFunctions as $function) {
            if (!is_callable($function)) {
                $missingFunctions[] = $function;
            }
        }

        if ($missingFunctions) {
            throw new \RuntimeException(sprintf("Following functions are required by %s but disabled in PHP: %s",
                    $className,
                    implode(", ", $missingFunctions)
                )
            );
        }

        return $this;
    }

    /**
     * @param EventInterface $event
     */
    public function onProcessTerminate(EventInterface $event)
    {
        $this->terminateTask($event->getParam('uid'), $event->getParam('soft'));
    }

    /**
     *
     */
    public function onServerInit()
    {
        // make the current process a session leader
        posix_setsid();
    }

    public function onSchedulerInit()
    {
        $onTaskTerminate = [$this, 'onSchedulerTerminate'];
        //pcntl_sigprocmask(SIG_BLOCK, [SIGCHLD]);
        pcntl_signal(SIGTERM, $onTaskTerminate);
        pcntl_signal(SIGQUIT, $onTaskTerminate);
        pcntl_signal(SIGTSTP, $onTaskTerminate);
        pcntl_signal(SIGINT, $onTaskTerminate);
        pcntl_signal(SIGHUP, $onTaskTerminate);
    }

    public function onSchedulerTerminate()
    {
        $this->events->trigger(EventsInterface::ON_SERVER_STOP, null, ['uid' => getmypid()]);

        exit();
    }

    public function sigBlock()
    {
        pcntl_sigprocmask(SIG_BLOCK, [SIGTERM]);
    }

    public function sigDispatch()
    {
        pcntl_signal_dispatch();
    }

    public function sigUnblock()
    {
        pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM]);
        $this->sigDispatch();
    }

    public function processSignals()
    {
        // catch other potential signals to avoid race conditions
        //while (($signal = \pcntl_sigtimedwait([SIGCHLD], $signalInfo, 0, 100)) > 0) {
        while (($pid = pcntl_wait($pcntlStatus, WNOHANG|WUNTRACED)) > 0) {
            if (pcntl_wifexited($pcntlStatus)) {
                $this->events->trigger(EventsInterface::ON_PROCESS_TERMINATED, null, ['uid' => $pid]);
            }
        }
        //}

        $this->sigDispatch();

        if ($this->ppid !== posix_getppid()) {
            $this->events->trigger(EventsInterface::ON_SERVER_STOP, null, ['uid' => $this->ppid]);

            exit();
        }
    }

    public function shutdownServer()
    {
        pcntl_wait($status, WUNTRACED);
        $this->sigDispatch();
    }

    public function startTask(EventInterface $event)
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new ProcessManagerException("Could not create a descendant process", ProcessManagerException::PROCESS_NOT_CREATED);
        } else if ($pid) {
            // we are the parent
            $event->setParam('uid', $pid);
            $this->events->trigger(EventsInterface::ON_PROCESS_CREATED, null, ['uid' => $pid]);

            return $this;
        } else {
            $pid = getmypid();
        }

        // we are the new process
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGHUP, SIG_DFL);
        pcntl_signal(SIGQUIT, SIG_DFL);
        pcntl_signal(SIGTSTP, SIG_DFL);

        $event->setParam('uid', $pid);
        $this->events->trigger(EventsInterface::ON_PROCESS_INIT, null, $event->getParams());

        return $this;
    }

    /**
     * @param int $pid
     * @param bool|false $useSoftTermination
     * @return $this
     */
    protected function terminateTask($pid, $useSoftTermination = false)
    {
        posix_kill($pid, $useSoftTermination ? SIGINT : SIGKILL);

        return $this;
    }

    /**
     * @param bool|false $useSoftTermination
     * @return $this
     */
    public function terminateAllTasks($useSoftTermination = false)
    {
        return $this->terminateTask(0, $useSoftTermination);
    }

    /**
     * @return MultiProcessingModuleCapabilities
     */
    public function getCapabilities()
    {
        $capabilities = new MultiProcessingModuleCapabilities();
        $capabilities->setIsolationLevel($capabilities::ISOLATION_PROCESS);

        return $capabilities;
    }
}