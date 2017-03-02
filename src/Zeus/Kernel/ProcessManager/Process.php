<?php

namespace Zeus\Kernel\ProcessManager;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Log\LoggerInterface;
use Zeus\Kernel\IpcServer\Message;
use Zeus\Kernel\ProcessManager\EventsInterface;
use Zeus\Kernel\ProcessManager\Status\ProcessState;

final class Process
{
    /** @var int Time to live before terminating (# of requests left till the auto-shutdown) */
    protected $ttl;

    /** @var ProcessState */
    protected $status;

    /** @var string */
    protected $id;

    /** @var EventManagerInterface */
    protected $events;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param string $uid
     * @return $this
     */
    public function setId($uid)
    {
        $this->id = $uid;

        return $this;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param EventManagerInterface $eventManager
     * @return $this
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $this->events = $eventManager;

        return $this;
    }

    /**
     * @param ConfigInterface $config
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        // set time to live counter
        $this->ttl = $config->getMaxProcessTasks();
        $this->status = new ProcessState($config->getServiceName());

        return $this;
    }

    /**
     * Process constructor.
     */
    public function __construct()
    {
        set_exception_handler([$this, 'terminateProcess']);
    }

    /**
     * @param int $type
     * @param mixed $message
     * @return $this
     */
    protected function sendMessage($type, $message)
    {
        $payload = [
            'isEvent' => false,
            'type' => $type,
            'priority' => $type,
            'message' => $message,
            'extra' => [
                'uid' => $this->getId(),
                'logger' => __CLASS__,
                'status' => $this->status->toArray()
            ]
        ];

        $this->events->trigger(EventsInterface::ON_PROCESS_MESSAGE, $this, $payload);

        return $this;
    }

    /**
     * @return $this
     */
    public function setBusy()
    {
        $this->status->incrementNumberOfFinishedTasks();
        $this->sendStatus(ProcessState::RUNNING);
        $this->events->trigger(EventsInterface::ON_PROCESS_RUNNING, $this, $this->status->toArray());

        return $this;
    }

    /**
     * @return $this
     */
    public function setIdle()
    {
        if ($this->status->getCode() === ProcessState::WAITING) {
            return $this;
        }

        $this->events->trigger(EventsInterface::ON_PROCESS_IDLING, $this, $this->status->toArray());
        $this->sendStatus(ProcessState::WAITING);

        return $this;
    }

    /**
     * @param \Exception $exception
     * @return $this
     */
    protected function reportException($exception)
    {
        $this->logger->err(sprintf("Exception (%d): %s in %s on line %d",
            $exception->getCode(),
            addcslashes($exception->getMessage(), "\t\n\r\0\x0B"),
            $exception->getFile(),
            $exception->getLine()
        ));
        $this->logger->debug(sprintf("Stack Trace:\n%s", $exception->getTraceAsString()));

        return $this;
    }

    /**
     * @param \Exception|\Throwable|null $exception
     */
    protected function terminateProcess($exception = null)
    {
        // child is dying, time to live equals zero
        // wake up the ServerDaemon to inform him that this child is dying
        $this->logger->debug(sprintf("Shutting down after finishing %d tasks", $this->status->getNumberOfFinishedTasks()));

        $this->ttl = 0;

        $this->sendStatus(ProcessState::EXITING);

        $payload = $this->status->toArray();

        if ($exception) {
            $payload['exception'] = $exception;
        }

        $this->events->trigger(EventsInterface::ON_PROCESS_EXIT, $this, $payload);
    }

    /**
     * Listen for incoming requests.
     *
     * @return $this
     */
    public function mainLoop()
    {
        $this->events->attach(EventsInterface::ON_PROCESS_LOOP, function(EventInterface $event) {
            $this->sendStatus($this->status->getCode());
        });

        $exception = null;
        $this->setIdle();

        // handle only a finite number of requests and terminate gracefully to avoid potential memory/resource leaks
        while ($this->ttl - $this->status->getNumberOfFinishedTasks() > 0) {
            $exception = null;
            try {
                $this->events->trigger(EventsInterface::ON_PROCESS_LOOP, $this, $this->status->toArray());
            } catch (\Exception $exception) {
                $this->reportException($exception);
            } catch (\Throwable $exception) {
                $this->reportException($exception);
            }
        }

        $this->terminateProcess();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $statusCode
     * @return $this
     */
    protected function sendStatus($statusCode)
    {
        $oldStatus = $this->status->getCode();
        $this->status->setCode($statusCode);
        $this->status->updateStatus();

        // send new status to Scheduler only if it changed
        if ($oldStatus !== $statusCode) {
            $this->sendMessage(Message::IS_STATUS, 'statusSent');
        }

        return $this;
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->events;
    }

    /**
     * @return ProcessState
     */
    public function getStatus()
    {
        return $this->status;
    }
}