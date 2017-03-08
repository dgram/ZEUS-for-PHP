<?php

namespace Zeus\Kernel\ProcessManager;

use Zend\Console\Console;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Log\LoggerInterface;
use Zeus\Kernel\IpcServer\Adapter\IpcAdapterInterface;
use Zeus\Kernel\ProcessManager\Exception\ProcessManagerException;
use Zeus\Kernel\ProcessManager\Helper\Logger;
use Zeus\Kernel\ProcessManager\Scheduler\ProcessCollection;
use Zeus\Kernel\ProcessManager\Status\ProcessState;
use Zeus\Kernel\IpcServer\Message;
use Zeus\Kernel\ProcessManager\Status\ProcessTitle;
use Zeus\Kernel\ProcessManager\Helper\EventManager;

final class Scheduler
{
    use Logger;
    use EventManager;

    /** @var ProcessState[]|ProcessCollection */
    protected $processes = [];

    /** @var Config */
    protected $config;

    /** @var float */
    protected $time;

    /** @var bool */
    protected $continueMainLoop = true;

    /** @var int */
    protected $id;

    /** @var ProcessState */
    protected $schedulerStatus;

    /** @var ProcessState */
    protected $processStatusTemplate;

    /** @var Process */
    protected $processService;

    /** @var IpcAdapterInterface */
    protected $ipcAdapter;

    /** @var ProcessTitle */
    protected $processTitle;

    /** @var SchedulerEvent */
    private $schedulerEvent;

    /** @var SchedulerEvent */
    private $processEvent;

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return float
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @param float $time
     * @return $this
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * @return bool
     */
    public function isContinueMainLoop()
    {
        return $this->continueMainLoop;
    }

    /**
     * @param bool $continueMainLoop
     * @return $this
     */
    public function setContinueMainLoop($continueMainLoop)
    {
        $this->continueMainLoop = $continueMainLoop;

        return $this;
    }

    /**
     * Handles input arguments.
     *
     * @param mixed[] $config
     * @param Process $processService
     * @param LoggerInterface $logger
     * @param IpcAdapterInterface $ipcAdapter
     * @param $schedulerEvent
     * @param $processEvent
     */
    public function __construct($config, Process $processService, LoggerInterface $logger, IpcAdapterInterface $ipcAdapter, $schedulerEvent, $processEvent)
    {
        $this->config = new Config($config);
        $this->ipcAdapter = $ipcAdapter;
        $this->processService = $processService;
<<<<<<< HEAD
        $this->schedulerStatus = new ProcessState($this->config->getServiceName());
        $this->processStatusTemplate = new ProcessState($this->config->getServiceName());
=======
        $this->status = new ProcessState($this->config->getServiceName());
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26

        $this->processes = new ProcessCollection($this->config->getMaxProcesses() + 1);
        $this->setLogger($logger);
        $this->setLoggerExtraDetails(['service' => $this->config->getServiceName()]);

        if (!Console::isConsole()) {
            throw new ProcessManagerException("This application must be launched from the Command Line Interpreter", ProcessManagerException::CLI_MODE_REQUIRED);
        }

        $this->processTitle = new ProcessTitle();
        $this->processTitle->attach($this->getEventManager());
        $this->schedulerEvent = $schedulerEvent;
        $this->processEvent = $processEvent;

        $this->schedulerEvent->setScheduler($this);
    }

    /**
     * @return IpcAdapterInterface
     */
    public function getIpcAdapter()
    {
        return $this->ipcAdapter;
    }

    /**
     * @param EventManagerInterface $events
     * @return $this
     */
    protected function attach(EventManagerInterface $events)
    {
<<<<<<< HEAD
        $events->attach(SchedulerEvent::EVENT_PROCESS_CREATED, function(EventInterface $e) { $this->addNewProcess($e);}, -10000);
        $events->attach(SchedulerEvent::EVENT_PROCESS_INIT, function(EventInterface $e) { $this->onProcessInit($e);});
        $events->attach(SchedulerEvent::EVENT_PROCESS_TERMINATED, function(EventInterface $e) { $this->onProcessExit($e);}, -10000);
        $events->attach(SchedulerEvent::EVENT_PROCESS_EXIT, function(EventInterface $e) { exit();}, -10000);
        $events->attach(SchedulerEvent::EVENT_PROCESS_MESSAGE, function(EventInterface $e) { $this->onProcessMessage($e);});
        $events->attach(SchedulerEvent::EVENT_SCHEDULER_STOP, function(EventInterface $e) { $this->onShutdown($e);});
        $events->attach(SchedulerEvent::EVENT_SCHEDULER_STOP, function(EventInterface $e) {
=======
        $events->attach(SchedulerEvent::PROCESS_CREATED, function(EventInterface $e) { $this->addNewProcess($e);}, -10000);
        $events->attach(SchedulerEvent::PROCESS_INIT, function(EventInterface $e) { $this->onProcessInit($e);});
        $events->attach(SchedulerEvent::PROCESS_TERMINATED, function(EventInterface $e) { $this->onProcessExit($e);}, -10000);
        $events->attach(SchedulerEvent::PROCESS_EXIT, function(EventInterface $e) { exit();}, -10000);
        $events->attach(SchedulerEvent::PROCESS_MESSAGE, function(EventInterface $e) { $this->onProcessMessage($e);});
        $events->attach(SchedulerEvent::SCHEDULER_STOP, function(EventInterface $e) { $this->onShutdown($e);});
        $events->attach(SchedulerEvent::SCHEDULER_STOP, function(EventInterface $e) {
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            /** @var \Exception $exception */
            $exception = $e->getParam('exception');

            $status = $exception ? $exception->getCode(): 0;
            exit($status);
        }, -10000);

<<<<<<< HEAD
        $events->attach(SchedulerEvent::EVENT_SCHEDULER_LOOP, function(EventInterface $e) {
=======
        $events->attach(SchedulerEvent::SCHEDULER_LOOP, function(EventInterface $e) {
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            $this->collectCycles();
            $this->handleMessages();
            $this->manageProcesses();
        });

        return $this;
    }

    /**
     * @param EventInterface $event
     */
    protected function onProcessMessage(EventInterface $event)
    {
        $this->ipcAdapter->send($event->getParams());
    }

    /**
     * @param EventInterface $event
     */
    protected function onProcessExit(EventInterface $event)
    {
        if ($event->getParam('uid') === $this->getId()) {
            $this->log(\Zend\Log\Logger::DEBUG, "Scheduler is exiting...");
            $event = $this->schedulerEvent;
<<<<<<< HEAD
            $event->setName(SchedulerEvent::EVENT_SCHEDULER_STOP);
=======
            $event->setName(SchedulerEvent::SCHEDULER_STOP);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            $event->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($event);
            return;
        }

        if ($this->isContinueMainLoop()) {
            $this->manageProcesses();
        }

        $this->terminateProcess($event->getParam('uid'));
    }

    /**
     * Stops the process manager.
     *
     * @return $this
     */
    public function stop()
    {
        $fileName = sprintf("%s%s.pid", $this->getConfig()->getIpcDirectory(), $this->getConfig()->getServiceName());

        if ($pid = @file_get_contents($fileName)) {
            $pid = (int)$pid;

            if ($pid) {
<<<<<<< HEAD
                $this->events->trigger(SchedulerEvent::EVENT_PROCESS_TERMINATE, $this,
=======
                $this->events->trigger(SchedulerEvent::PROCESS_TERMINATE, $this,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
                    $this->getEventExtraData([
                        'uid' => $pid,
                        'soft' => true,
                    ]
                    ));
                $this->log(\Zend\Log\Logger::INFO, "Server stopped");
                unlink($fileName);

                return $this;
            }
        }

        throw new ProcessManagerException("Server not running", ProcessManagerException::SERVER_NOT_RUNNING);
    }

    /**
     * @return mixed[]
     */
    public function getStatus()
    {
        $payload = [
            'isEvent' => false,
            'type' => Message::IS_STATUS_REQUEST,
            'priority' => '',
            'message' => 'fetchStatus',
            'extra' => [
                'uid' => $this->getId(),
                'logger' => __CLASS__
            ]
        ];

        $this->ipcAdapter->useChannelNumber(1);
        $this->ipcAdapter->send($payload);

        $timeout = 5;
        $result = null;
        do {
            $result = $this->ipcAdapter->receive();
            usleep(1000);
            $timeout--;
        } while (!$result && $timeout >= 0);

        $this->ipcAdapter->useChannelNumber(0);

        if ($result) {
            return $result['extra'];
        }

        return null;
    }

    /**
     * @param mixed[] $extraExtraData
     * @return mixed[]
     */
    private function getEventExtraData($extraExtraData = [])
    {
        $extraExtraData = array_merge($this->schedulerStatus->toArray(), $extraExtraData, ['service_name' => $this->config->getServiceName()]);
        return $extraExtraData;
    }

    /**
     * Creates the server instance.
     *
     * @param bool $launchAsDaemon Run this server as a daemon?
     * @return $this
     */
    public function start($launchAsDaemon = false)
    {
        $this->log(\Zend\Log\Logger::INFO, "Starting server");
        $this->collectCycles();

        $events = $this->getEventManager();
<<<<<<< HEAD
        $events->attach(SchedulerEvent::EVENT_SCHEDULER_START, [$this, 'startScheduler'], 0);
=======
        $events->attach(SchedulerEvent::SCHEDULER_START, [$this, 'startScheduler'], 0);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
        $schedulerEvent = $this->schedulerEvent;
        $processEvent = $this->processEvent;

        try {
            if (!$launchAsDaemon) {
<<<<<<< HEAD
                $this->getEventManager()->trigger(SchedulerEvent::INTERNAL_EVENT_KERNEL_START, $this, $this->getEventExtraData());
                $this->getEventManager()->trigger(SchedulerEvent::EVENT_SCHEDULER_START, $this, $this->getEventExtraData());
=======
                $this->getEventManager()->trigger(SchedulerEvent::SERVER_START, $this, $this->getEventExtraData());
                $this->getEventManager()->trigger(SchedulerEvent::SCHEDULER_START, $this, $this->getEventExtraData());
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26

                return $this;
            }

<<<<<<< HEAD
            $events->attach(SchedulerEvent::EVENT_PROCESS_INIT, function(EventInterface $e) {
                if ($e->getParam('server')) {
                    $e->stopPropagation(true);
                    $this->getEventManager()->trigger(SchedulerEvent::EVENT_SCHEDULER_START, $this, $this->getEventExtraData());
                }
            }, 100000);

            $events->attach(SchedulerEvent::EVENT_PROCESS_CREATE,
=======
            $events->attach(SchedulerEvent::PROCESS_INIT, function(EventInterface $e) {
                if ($e->getParam('server')) {
                    $e->stopPropagation(true);
                    $this->getEventManager()->trigger(SchedulerEvent::SCHEDULER_START, $this, $this->getEventExtraData());
                }
            }, 100000);

            $events->attach(SchedulerEvent::PROCESS_CREATE,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
                function (EventInterface $event) {
                    $pid = $event->getParam('uid');

                    if (!$event->getParam('server')) {
                        return;
                    }

                    if (!@file_put_contents(sprintf("%s%s.pid", $this->getConfig()->getIpcDirectory(), $this->config->getServiceName()), $pid)) {
                        throw new ProcessManagerException("Could not write to PID file, aborting", ProcessManagerException::LOCK_FILE_ERROR);
                    }

                    $event->stopPropagation(true);
                }
                , -10000
            );

<<<<<<< HEAD
            $processEvent->setName(SchedulerEvent::EVENT_PROCESS_CREATE);
            $processEvent->setParams($this->getEventExtraData(['server' => true]));
            $this->getEventManager()->triggerEvent($processEvent);

            $schedulerEvent->setName(SchedulerEvent::INTERNAL_EVENT_KERNEL_START);
            $schedulerEvent->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($schedulerEvent);
        } catch (\Throwable $e) {
            $schedulerEvent->setName(SchedulerEvent::EVENT_SCHEDULER_STOP);
            $schedulerEvent->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($schedulerEvent);
        } catch (\Exception $e) {
            $schedulerEvent->setName(SchedulerEvent::EVENT_SCHEDULER_STOP);
=======
            $processEvent->setName(SchedulerEvent::PROCESS_CREATE);
            $processEvent->setParams($this->getEventExtraData(['server' => true]));
            $this->getEventManager()->triggerEvent($processEvent);

            $schedulerEvent->setName(SchedulerEvent::SERVER_START);
            $schedulerEvent->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($schedulerEvent);
        } catch (\Throwable $e) {
            $schedulerEvent->setName(SchedulerEvent::SCHEDULER_STOP);
            $schedulerEvent->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($schedulerEvent);
        } catch (\Exception $e) {
            $schedulerEvent->setName(SchedulerEvent::SCHEDULER_STOP);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            $schedulerEvent->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($schedulerEvent);
        }

        return $this;
    }

    /**
     * @param EventInterface $event
     * @return $this
     */
    public function startScheduler(EventInterface $event)
    {
        $this->log(\Zend\Log\Logger::DEBUG, "Scheduler starting...");

        $this->setId(getmypid());

        $this->attach($this->getEventManager());

        $config = $this->getConfig();

        if ($config->isProcessCacheEnabled()) {
            $this->createProcesses($config->getStartProcesses());
        }

        $this->log(\Zend\Log\Logger::INFO, "Scheduler started");

        return $this->mainLoop();
    }

    /**
     * @param string $pid
     * @return $this
     */
    public function terminateProcess($pid)
    {
        $this->log(\Zend\Log\Logger::DEBUG, "Process $pid exited");

        if (isset($this->processes[$pid])) {
            $processStatus = $this->processes[$pid];

            if (!ProcessState::isExiting($processStatus) && $processStatus['time'] < microtime(true) - $this->getConfig()->getProcessIdleTimeout()) {
                $this->log(\Zend\Log\Logger::ERR, "Process $pid exited prematurely");
            }

            unset($this->processes[$pid]);
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function collectCycles()
    {
        $enabled = gc_enabled();
        gc_enable();
        if (function_exists('gc_mem_caches')) {
            // @codeCoverageIgnoreStart
            gc_mem_caches();
            // @codeCoverageIgnoreEnd
        }
        gc_collect_cycles();


        if (!$enabled) {
            // @codeCoverageIgnoreStart
            gc_disable();
            // @codeCoverageIgnoreEnd
        }

        return $this;
    }

    /**
     * Shutdowns the server
     *
     * @param EventInterface $event
     * @return $this
     */
    protected function onShutdown(EventInterface $event)
    {
        $exception = $event->getParam('exception', null);

        $this->setContinueMainLoop(false);

        $this->log(\Zend\Log\Logger::INFO, "Terminating scheduler");

        $processes = count($this->processes);

        foreach ($this->processes as $pid => $value) {
            if (!$pid) {
                continue;
            }

            $this->log(\Zend\Log\Logger::DEBUG, "Terminating process $pid");
<<<<<<< HEAD
            $this->events->trigger(SchedulerEvent::EVENT_PROCESS_TERMINATE, $this, $this->getEventExtraData(['uid' => $pid]));
=======
            $this->events->trigger(SchedulerEvent::PROCESS_TERMINATE, $this, $this->getEventExtraData(['uid' => $pid]));
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
        }

        $this->handleMessages();

        if ($exception) {
            $status = $exception->getCode();
            $this->log(\Zend\Log\Logger::ERR, sprintf("Exception (%d): %s in %s:%d", $status, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
        }

        $this->log(\Zend\Log\Logger::INFO, "Terminated scheduler with $processes processes");

        $this->ipcAdapter->disconnect();
    }

    /**
     * Forks children
     *
     * @param int $count Number of processes to create.
     * @return $this
     */
    protected function createProcesses($count)
    {
        if ($count === 0) {
            return $this;
        }

        $this->setTime(microtime(true));

        for ($i = 0; $i < $count; ++$i) {
            $event = $this->processEvent;
<<<<<<< HEAD
            $event->setName(SchedulerEvent::EVENT_PROCESS_CREATE);
=======
            $event->setName(SchedulerEvent::PROCESS_CREATE);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            $event->setParams($this->getEventExtraData());
            $this->getEventManager()->triggerEvent($event);
        }

        return $this;
    }

    /**
     * @param SchedulerEvent $event
     */
    protected function onProcessInit(SchedulerEvent $event)
    {
        $pid = $event->getParam('uid');

        unset($this->processes);
        $this->collectCycles();
        $this->setContinueMainLoop(false);
        $this->ipcAdapter->useChannelNumber(1);


        $event->setProcess($this->processService);
        $this->processService->setId($pid);
        $this->processService->setEventManager($this->getEventManager());
        $this->processService->setConfig($this->getConfig());
        $this->processService->mainLoop();
    }

    /**
     * @param EventInterface $event
     */
    protected function addNewProcess(EventInterface $event)
    {
        $pid = $event->getParam('uid');

        $this->processes[$pid] = [
            'code' => ProcessState::WAITING,
            'uid' => $pid,
            'time' => microtime(true),
            'service_name' => $this->config->getServiceName(),
            'requests_finished' => 0,
            'requests_per_second' => 0,
            'cpu_usage' => 0,
            'status_description' => '',
        ];
    }

    /**
     * Manages server processes.
     *
     * @return $this
     */
    protected function manageProcesses()
    {
        $config = $this->getConfig();
        $this->setTime(microtime(true));

        /**
         * Time after which idle process should be ultimately terminated.
         *
         * @var float
         */
        $expireTime = $this->getTime() - $config->getProcessIdleTimeout();

        $statusSummary = $this->processes->getStatusSummary();
        $idleProcesses = $statusSummary[ProcessState::WAITING];
        $busyProcesses = $statusSummary[ProcessState::RUNNING];
        //$terminatedProcesses = $statusSummary[ProcessStatus::STATUS_EXITING] + $statusSummary[ProcessStatus::STATUS_KILL];
        $allProcesses = $this->processes->count();

        if (!$this->isContinueMainLoop() || !$config->isProcessCacheEnabled()) {
            return $this;
        }

        // terminate idle processes, if number of them is too high.
        if ($idleProcesses > $config->getMaxSpareProcesses()) {
            $toTerminate = $idleProcesses - $config->getMaxSpareProcesses();
            $terminated = 0;

            foreach ($this->processes as $pid => $processStatus) {
                if (!$processStatus || !ProcessState::isIdle($processStatus)) {
                    continue;
                }

                if ($processStatus['time'] < $expireTime) {
                    $processStatus['code'] = ProcessState::TERMINATED;
                    $processStatus['time'] = $this->getTime();
                    $this->processes[$pid] = $processStatus;

                    $this->log(\Zend\Log\Logger::DEBUG, sprintf('Terminating idle process %d', $pid));
<<<<<<< HEAD
                    $this->events->trigger(SchedulerEvent::EVENT_PROCESS_TERMINATE, $this, $this->getEventExtraData(['uid' => $pid, 'soft' => true]));
=======
                    $this->events->trigger(SchedulerEvent::PROCESS_TERMINATE, $this, $this->getEventExtraData(['uid' => $pid, 'soft' => true]));
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26

                    ++$terminated;

                    if ($terminated === $toTerminate || $terminated === $config->getMaxSpareProcesses()) {
                        break;
                    }
                }
            }
        }

        // start additional processes, if number of them is too small.
        if ($idleProcesses < $config->getMinSpareProcesses()) {
            $idleProcessSlots = $this->processes->getSize() - $this->processes->count();

            $processAmountToCreate = min($idleProcessSlots, $config->getMinSpareProcesses());

            if ($processAmountToCreate > 0) {
                $this->createProcesses($processAmountToCreate);
            }

            return $this;
        }

        if ($allProcesses === 0 && $config->getMinSpareProcesses() === 0 && $config->getMaxSpareProcesses() > 0) {
            $this->createProcesses($config->getMaxSpareProcesses());

            return $this;
        }

        return $this;
    }

    /**
     * Creates main (infinite) loop.
     *
     * @return $this
     */
    protected function mainLoop()
    {
        while ($this->isContinueMainLoop()) {
<<<<<<< HEAD
            $this->events->trigger(SchedulerEvent::EVENT_SCHEDULER_LOOP, $this, $this->getEventExtraData());
=======
            $this->events->trigger(SchedulerEvent::SCHEDULER_LOOP, $this, $this->getEventExtraData());
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
        }

        return $this;
    }

    /**
     * Handles messages.
     *
     * @return $this
     */
    protected function handleMessages()
    {
        $this->schedulerStatus->updateStatus();

        /** @var Message[] $messages */
        $this->ipcAdapter->useChannelNumber(0);

        $messages = $this->ipcAdapter->receiveAll();
        $this->setTime(microtime(true));

        foreach ($messages as $message) {
            switch ($message['type']) {
                case Message::IS_STATUS:
                    $details = $message['extra'];
                    $pid = $details['uid'];

                    /** @var ProcessState $processStatus */
                    $processStatus = $message['extra']['status'];
                    $processStatus['time'] = $this->getTime();

                    if ($processStatus['code'] === ProcessState::RUNNING) {
                        $this->schedulerStatus->incrementNumberOfFinishedTasks();
                    }

                    // child status changed, update this information server-side
                    if (isset($this->processes[$pid])) {
                        $this->processes[$pid] = $processStatus;
                    }

                    break;

                case Message::IS_STATUS_REQUEST:
                    $this->logger->debug('Status request detected');
                    $this->sendSchedulerStatus($this->ipcAdapter);
                    break;

                default:
                    $this->logMessage($message);
                    break;
            }
        }

        return $this;
    }

    private function sendSchedulerStatus(IpcAdapterInterface $ipcAdapter)
    {
        $payload = [
            'isEvent' => false,
            'type' => Message::IS_STATUS,
            'priority' => '',
            'message' => 'statusSent',
            'extra' => [
                'uid' => $this->getId(),
                'logger' => __CLASS__,
                'process_status' => $this->processes->toArray(),
                'scheduler_status' => $this->schedulerStatus->toArray(),
            ]
        ];

        $payload['extra']['scheduler_status']['total_traffic'] = 0;
        $payload['extra']['scheduler_status']['start_timestamp'] = $_SERVER['REQUEST_TIME_FLOAT'];

        $ipcAdapter->send($payload);
    }
}