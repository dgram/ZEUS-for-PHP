<?php

namespace Zeus\Kernel\ProcessManager;

/**
 * Server configuration class.
 */
class Config implements ConfigInterface
{
    /**
     * Number of workers to create at the start of a server.
     *
     * @var int
     */
    protected $startProcesses = 3;

    /**
     * Maximal number of workers for this server.
     *
     * @var int
     */
    protected $maxProcesses = 255;

    /**
     * Minimal number of idle tasks.
     *
     * @var int
     */
    protected $minSpareProcesses = 3;

    /**
     * Maximal number of idle workers.
     *
     * @var int
     */
    protected $maxSpareProcesses = 5;

    /**
     * Minimum idle time (in seconds), before idle process can be terminated.
     *
     * @var int
     */
    protected $processIdleTimeout = 10;

    /**
     * Maximal number of tasks handled by a single process, before it will be killed to avoid potential memory leaks.
     *
     * @var int
     */
    protected $maxProcessTasks = 20000;

    /** @var string */
    protected $dataDirectory;

    /** @var string */
    protected $ipcDirectory;

    /** @var bool */
    protected $isProcessCacheEnabled = false;

    /** @var string */
    protected $serviceName;

    /** @var mixed[] */
    protected $arrayValues;

    /** @var bool */
    protected $isAutoStartEnabled = true;

    /**
     * Config constructor.
     * @param mixed[]|ConfigInterface $fromArray
     */
    public function __construct($fromArray = null)
    {
        if ($fromArray instanceof ConfigInterface) {
            $fromArray = $fromArray->toArray();
        }

        if (isset($fromArray['max_processes'])) {
            $this->setMaxProcesses($fromArray['max_processes']);
        }

        if (isset($fromArray['max_process_tasks'])) {
            $this->setMaxProcessTasks($fromArray['max_process_tasks']);
        }

        if (isset($fromArray['min_spare_processes'])) {
            $this->setMinSpareProcesses($fromArray['min_spare_processes']);
        }

        if (isset($fromArray['max_spare_processes'])) {
            $this->setMaxSpareProcesses($fromArray['max_spare_processes']);
        }

        if (isset($fromArray['start_processes'])) {
            $this->setStartProcesses($fromArray['start_processes']);
        }

        if (isset($fromArray['enable_process_cache'])) {
            $this->setIsProcessCacheEnabled($fromArray['enable_process_cache']);
        }

        if (isset($fromArray['auto_start'])) {
            $this->setIsAutoStartEnabled($fromArray['auto_start']);
        }

        if (isset($fromArray['service_name'])) {
            $this->setServiceName($fromArray['service_name']);
        }

        $this->arrayValues = $fromArray;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->arrayValues[$name]) ? $this->arrayValues[$name] : null;
    }

    /**
     * @return mixed[]
     */
    public function toArray()
    {
        return $this->arrayValues;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     * @return Config
     */
    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoStartEnabled()
    {
        return $this->isAutoStartEnabled;
    }

    /**
     * @param bool $isAutoStartEnabled
     * @return $this
     */
    public function setIsAutoStartEnabled($isAutoStartEnabled)
    {
        $this->isAutoStartEnabled = $isAutoStartEnabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isProcessCacheEnabled()
    {
        return $this->isProcessCacheEnabled;
    }

    /**
     * @param bool $isEnabled
     * @return $this
     */
    public function setIsProcessCacheEnabled($isEnabled)
    {
        $this->isProcessCacheEnabled = $isEnabled;

        return $this;
    }

    /**
     * @return int
     */
    public function getStartProcesses()
    {
        return $this->startProcesses;
    }

    /**
     * @param int $minStartingProcesses
     * @return $this
     */
    public function setStartProcesses($minStartingProcesses)
    {
        $this->startProcesses = $minStartingProcesses;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxProcesses()
    {
        return $this->maxProcesses;
    }

    /**
     * @param int $maxProcesses
     * @return $this
     */
    public function setMaxProcesses($maxProcesses)
    {
        $this->maxProcesses = $maxProcesses;

        return $this;
    }

    /**
     * @return int
     */
    public function getMinSpareProcesses()
    {
        return $this->minSpareProcesses;
    }

    /**
     * @param int $minSpareProcessesLimit
     * @return $this
     */
    public function setMinSpareProcesses($minSpareProcessesLimit)
    {
        $this->minSpareProcesses = $minSpareProcessesLimit;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxSpareProcesses()
    {
        return $this->maxSpareProcesses;
    }

    /**
     * @param int $maxSpareProcessesLimit
     * @return $this
     */
    public function setMaxSpareProcesses($maxSpareProcessesLimit)
    {
        $this->maxSpareProcesses = $maxSpareProcessesLimit;

        return $this;
    }

    /**
     * @return int
     */
    public function getProcessIdleTimeout()
    {
        return $this->processIdleTimeout;
    }

    /**
     * @param int $timeInSeconds
     * @return $this
     */
    public function setProcessIdleTimeout($timeInSeconds)
    {
        $this->processIdleTimeout = $timeInSeconds;

        return $this;
    }

    /**
     * @return string
     */
    public function getDataDirectory()
    {
        if (!$this->dataDirectory) {
            $this->setDataDirectory(getcwd() . DIRECTORY_SEPARATOR);
        }

        return $this->dataDirectory;
    }

    /**
     * @param string $directory
     * @return $this
     */
    public function setDataDirectory($directory)
    {
        $this->dataDirectory = $directory;

        return $this;
    }

    /**
     * @return string
     */
    public function getIpcDirectory()
    {
        if (!$this->ipcDirectory) {
            $this->setIpcDirectory($this->getDataDirectory() . DIRECTORY_SEPARATOR);
        }

        return $this->ipcDirectory;
    }

    /**
     * @param string $fileName
     * @return $this
     */
    public function setIpcDirectory($fileName)
    {
        $this->ipcDirectory = $fileName;

        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function setMaxProcessTasks($limit)
    {
        $this->maxProcessTasks = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxProcessTasks()
    {
        return $this->maxProcessTasks;
    }

    /**
     * @param mixed $name
     * @return bool
     */
    public function offsetExists($name)
    {
        return isset($this->arrayValues[$name]);
    }

    /**
     * @param mixed $name
     * @return mixed|null
     */
    public function offsetGet($name)
    {
        return isset($this->arrayValues[$name]) ? $this->arrayValues[$name] : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException("Config object is read only");
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException("Config object is read only");
    }
}