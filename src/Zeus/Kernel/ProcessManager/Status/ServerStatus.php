<?php

namespace Zeus\Kernel\ProcessManager\Status;

class ServerStatus
{
    /**
     * @var integer
     */
    protected $startTime = 0;

    /**
     * @var integer
     */
    protected $totalRequests = 0;

    /**
     * @var integer
     */
    protected $totalTraffic = 0;

    /**
     * @var float
     */
    protected $userCpuTime = 0;

    /**
     * @var float
     */
    protected $systemCpuTime = 0;

    /**
     * @var float
     */
    protected $currentUserCpuTime = 0;

    /**
     * @var float
     */
    protected $currentSystemCpuTime = 0;

    /**
     * @var ProcessState[]
     */
    protected $processesStatusLists = 0;

    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getTotalTraffic()
    {
        return $this->totalTraffic;
    }

    /**
     * @param int $totalTraffic
     * @return $this
     */
    public function setTotalTraffic($totalTraffic)
    {
        $this->totalTraffic = $totalTraffic;

        return $this;
    }

    /**
     * @param int $trafficDelta
     * @return $this
     */
    public function incrementTotalTraffic($trafficDelta)
    {
        $this->totalTraffic += $trafficDelta;

        return $this;
    }

    /**
     * @return float
     */
    public function getUserCpuTime()
    {
        return $this->userCpuTime;
    }

    /**
     * @param float $userCpuTime
     * @return $this
     */
    public function setUserCpuTime($userCpuTime)
    {
        $this->userCpuTime = $userCpuTime;

        return $this;
    }

    /**
     * @param float $userCpuTime
     * @return $this
     */
    public function incrementUserCpuTime($userCpuTime)
    {
        $this->userCpuTime += $userCpuTime;

        return $this;
    }

    /**
     * @return float
     */
    public function getSystemCpuTime()
    {
        return $this->systemCpuTime;
    }

    /**
     * @param float $systemCpuTime
     * @return $this
     */
    public function setSystemCpuTime($systemCpuTime)
    {
        $this->systemCpuTime = $systemCpuTime;

        return $this;
    }

    /**
     * @param float $systemCpuTime
     * @return $this
     */
    public function incrementSystemCpuTime($systemCpuTime)
    {
        $this->systemCpuTime += $systemCpuTime;

        return $this;
    }

    /**
     * @return int
     */
    public function getUptime()
    {
        return time() - $this->getStartTime();
    }

    /**
     * @return int
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param int $startTime
     * @return $this
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalRequests()
    {
        return $this->totalRequests;
    }

    /**
     * @param int $totalRequests
     * @return $this
     */
    public function setTotalRequests($totalRequests)
    {
        $this->totalRequests = $totalRequests;

        return $this;
    }

    /**
     * @param int $requestsDelta
     * @return $this
     */
    public function incrementTotalRequests($requestsDelta)
    {
        $this->totalRequests += $requestsDelta;

        return $this;
    }

    /**
     * @return ProcessState[]
     */
    public function getProcessesStatusLists()
    {
        return $this->processesStatusLists;
    }

    /**
     * @param ProcessState[] $processesStatusLists
     * @return $this
     */
    public function setProcessesStatusLists($processesStatusLists)
    {
        $this->processesStatusLists = $processesStatusLists;

        return $this;
    }

    /**
     * @return $this
     */
    public function updateStatus()
    {
        $this->updateCurrentCpuTime();

        return $this;
    }

    /**
     * @return $this
     */
    protected function updateCurrentCpuTime()
    {
        if (!function_exists('getrusage')) {
            $usage = [
                "ru_stime.tv_sec" => 0,
                "ru_utime.tv_sec" => 0,
                "ru_stime.tv_usec" => 0,
                "ru_utime.tv_usec" => 0,
            ];
        } else {
            $usage = getrusage(1);
        }

        $this->currentSystemCpuTime = $usage["ru_stime.tv_sec"] * 1e6 + $usage["ru_stime.tv_usec"];
        $this->currentUserCpuTime = $usage["ru_utime.tv_sec"] * 1e6 + $usage["ru_utime.tv_usec"];

        return $this;
    }

    /**
     * @return float
     */
    public function getCurrentSystemCpuTime()
    {
        return $this->currentSystemCpuTime;
    }

    /**
     * @return float
     */
    public function getCurrentUserCpuTime()
    {
        return $this->currentUserCpuTime;
    }

    /**
     * @return float
     */
    public function getCpuUsage()
    {
        $uptime = max($this->getUptime(), 0.000000001) * 1e6;

        $cpuTime = $this->getCurrentSystemCpuTime() + $this->getCurrentUserCpuTime();

        $cpuUsage = min($cpuTime / $uptime, 1) * 100;

        return $cpuUsage;
    }
}