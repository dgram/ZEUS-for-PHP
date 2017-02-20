<?php

namespace Zeus\Kernel\ProcessManager\Status;

use Zend\Console\ColorInterface;
use Zeus\Kernel\ProcessManager\Scheduler;

class ServerStatusView
{
    /**
     * @var Scheduler
     */
    protected $server;

    public function __construct(Scheduler $server)
    {
        $this->server = $server;
    }

    /**
     * @return Scheduler
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param Scheduler $server
     * @return ServerStatusView
     */
    public function setServer($server)
    {
        $this->server = $server;
        return $this;
    }

    public function display($waitForStatus = false)
    {
        $serverStatus = $this->getServer()->getServerStatus($waitForStatus, true);
        $statusTab = '';

        $idleChildren = 0;
        $exitingChildren = 0;
        $busyChildren = 0;
        $allChildren = 0;
        $currentCpuUsage = 0;
        $currentUserCpuUsage = 0;
        $currentSystemCpuUsage = 0;


        /**
         * @var ProcessState $processStatus
         */
        foreach ($serverStatus->getProcessesStatusLists() as $processStatus) {
            $currentCpuUsage += $processStatus->getCpuUsage();
            $currentUserCpuUsage += $processStatus->getCurrentUserCpuTime();
            $currentSystemCpuUsage += $processStatus->getCurrentSystemCpuTime();

            switch ($processStatus->getCode()) {
                case ProcessState::WAITING:
                    ++$idleChildren;
                    ++$allChildren;
                    $statusTab .= "_";
                    break;

                case ProcessState::RUNNING:
                    ++$busyChildren;
                    ++$allChildren;
                    $statusTab .= "W";
                    break;

                case ProcessState::EXITING:
                    ++$exitingChildren;
                    ++$allChildren;
                    $statusTab .= "G";
                    break;

                default:
                    break;
            }
        }

        $statusTab = str_pad($statusTab, $this->getServer()->getConfig()->getMaxProcesses(), '.', STR_PAD_RIGHT);

        $statusLines = str_split($statusTab, 64);
        $uptime = time() - $serverStatus->getStartTime();

        $console = $this->getServer()->getConfig()->getConsole();

        $console->writeLine("Server Status", ColorInterface::GREEN);
        $console->writeLine("");
        $console->writeLine(sprintf("Current time: %s", $this->getDate(time())));
        $console->writeLine(sprintf("Restart time: %s", $this->getDate($serverStatus->getStartTime())));
        $console->writeLine(sprintf("Server uptime: %s", $this->getDateDiff($serverStatus->getStartTime(), microtime(true))));
        $console->writeLine(sprintf("Total accesses: %d - Total Traffic: %.1f MB",
            $serverStatus->getTotalRequests(),
            $serverStatus->getTotalTraffic()
        ));

        $currentSystemCpuUsage /= 1e6;
        $currentUserCpuUsage /= 1e6;

        $console->writeLine(sprintf("CPU Usage: u%.1f s%.1f cu%.1f cs%.1f - %.1f%s CPU load",
            $serverStatus->getUserCpuTime() / 1e6 + $currentUserCpuUsage,
            $serverStatus->getSystemCpuTime() / 1e6 + $currentSystemCpuUsage,
            //$serverStatus->getCurrentUserCpuTime(),
            $currentUserCpuUsage,
            //$serverStatus->getCurrentSystemCpuTime(),
            $currentSystemCpuUsage,
            //$serverStatus->getCpuUsage()
            min(100, $currentCpuUsage / max(1, count($serverStatus->getProcessesStatusLists()))),
            '%'
        ));
        $console->writeLine(sprintf("%.1f requests/sec - %.1f MB/second - %.1f MB/request",
            $serverStatus->getTotalRequests() / $uptime,
            $serverStatus->getTotalTraffic() / $uptime,
            $serverStatus->getTotalTraffic() / max($serverStatus->getTotalRequests(), 1)
        ));

        $console->writeLine(sprintf("%d requests currently being processed, %d idle workers", $busyChildren, $idleChildren));
        $console->writeLine("");

        foreach ($statusLines as $line) {
            $console->writeLine($line);
        }

        $console->writeLine("");

        $console->writeLine("Scoreboard Key:");
        $console->writeLine('"_" Waiting for Connection, "W" Currently working, "Z" Zombie process,');
        $console->writeLine('"G" Gracefully finishing, "." Open slot with no current process');
    }

    protected function getDate($time)
    {
        $timeZone = date_default_timezone_get();
        $timeZone = new \DateTimeZone(!empty($timeZone) ? $timeZone : 'GMT');
        $dateTime = new \DateTime();
        $dateTime->setTimeZone($timeZone);

        return sprintf("%s %s", date('l, d-M-Y H:i:s', $time), $dateTime->format('T'));
    }

    protected function getDateDiff($startTime, $endTime)
    {
        $startTime = (int) $startTime;
        $endTime = (int) $endTime;

        $dateTime1 = new \DateTime("@$startTime");
        $dateTime2 = new \DateTime("@$endTime");

        $interval = date_diff($dateTime1, $dateTime2);

        $date = [
            'years' => $this->formatDateSegment($interval->format('%y'), 'year', 'years'),
            'months' => $this->formatDateSegment($interval->format('%m'), 'month', 'months'),
            'days' => $this->formatDateSegment($interval->format('%d'), 'day', 'days'),
            'hours' => $this->formatDateSegment($interval->format('%h'), 'hour', 'hours'),
            'minutes' => $this->formatDateSegment($interval->format('%i'), 'minute', 'minutes'),
            'seconds' => $this->formatDateSegment($interval->format('%s'), 'second', 'seconds'),
        ];

        $date = array_filter($date);

        return implode(" ", $date);
    }

    private function formatDateSegment($value, $singularForm, $pluralForm)
    {
        return $value = $value ? ($value > 1 ? "$value $pluralForm" : "$value $singularForm") : "";
    }
}