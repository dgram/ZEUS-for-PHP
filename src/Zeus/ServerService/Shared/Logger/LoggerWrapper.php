<?php

namespace Zeus\ServerService\Shared\Logger;

use Traversable;
use Zend\Log\LoggerInterface as ZendLoggerInterface;

class LoggerWrapper implements LoggerInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /**
     * ZendLogWrapper constructor.
     * @param ZendLoggerInterface $logger
     */
    public function __construct(ZendLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function emerg($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->emerg($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function alert($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->alert($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function crit($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->crit($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function err($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->err($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function warn($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->warn($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function notice($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->notice($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function info($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->info($message, $extra);
    }

    /**
     * @param string $message
     * @param array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function debug($message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->debug($message, $extra);
    }

    /**
     * Add a message as a log entry
     *
     * @param  int $priority
     * @param  mixed $message
     * @param  array|Traversable $extra
     * @return ZendLoggerInterface
     */
    public function log($priority, $message, $extra = [])
    {
        $extra['logger'] = $this->getLoggerName($extra);
        return $this->logger->log($priority, $message, $extra);
    }

    /**
     * @param  mixed[]|Traversable $extra
     * @return string
     */
    protected function getLoggerName($extra)
    {
        if (isset($extra['logger'])) {
            return $extra['logger'];
        }

        $trace = debug_backtrace(false, 3);

        $traceStep = $trace[2];

        return isset($traceStep['class']) ? $traceStep['class'] : $traceStep['function'];
    }

    /**
     * @param string $name
     * @param mixed[] $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array([$this->logger, $name], $args);
    }
}