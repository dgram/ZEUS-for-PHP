<?php

namespace ZeusTest;

use PHPUnit_Framework_TestCase;
use Zend\Console\Console;
use Zend\EventManager\Event;
use Zend\EventManager\EventInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Mock;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\SplPriorityQueue;
use Zend\Stdlib\SplQueue;
use Zeus\Kernel\ProcessManager\Exception\ProcessManagerException;
use Zeus\Kernel\ProcessManager\Scheduler;
use Zeus\Kernel\ProcessManager\EventsInterface;
use ZeusTest\Helpers\ZeusFactories;

class SchedulerTest extends PHPUnit_Framework_TestCase
{
    use ZeusFactories;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    public function setUp()
    {
        Console::overrideIsConsole(true);
        chdir(__DIR__);
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testCliDetection()
    {
        Console::overrideIsConsole(false);

        try {
            $this->getScheduler();
        } catch (\Exception $e) {
            $this->assertInstanceOf(ServiceNotCreatedException::class, $e);
            $this->assertInstanceOf(ProcessManagerException::class, $e->getPrevious());
        }
    }

    public function testApplicationInit()
    {
        $scheduler = $this->getScheduler();
        $this->assertInstanceOf(Scheduler::class, $scheduler);
        $scheduler->setContinueMainLoop(false);
        $scheduler->startScheduler(new Event());
        $this->assertEquals(getmypid(), $scheduler->getId());
    }

    public function testMainLoopIteration()
    {
        $scheduler = $this->getScheduler();
        $this->assertInstanceOf(Scheduler::class, $scheduler);

        $events = $scheduler->getEventManager();
        $counter = 0;
        $events->attach(EventsInterface::ON_SCHEDULER_LOOP, function(EventInterface $e) use (&$counter) {
            $e->getTarget()->setContinueMainLoop(false);
            $counter++;
        });

        $scheduler->startScheduler(new Event());
        $this->assertEquals(1, $counter, 'Loop should have been executed only once');
    }

    public function testIpcLogDispatching()
    {
        $scheduler = $this->getScheduler(1);
        $logger = $scheduler->getLogger();
        $ipc = $scheduler->getIpcAdapter();

        $messages = [];
        foreach (["debug", "warn", "err", "alert", "info", "crit", "notice", "emerg"] as $severity) {
            $message = sprintf("%s message", ucfirst($severity));
            $logger->$severity($message);
            $messages[strtoupper($severity)] = $message;
        }

        $mockWriter = new Mock();
        $nullLogger = new Logger();
        $nullLogger->addWriter($mockWriter);
        $scheduler->setLogger($nullLogger);

        $ipc->useChannelNumber(0);

        $this->assertInstanceOf(Scheduler::class, $scheduler);

        $scheduler->startScheduler(new Event());
        $ipc->useChannelNumber(1);
        $this->assertEquals(0, count($ipc->receiveAll()), "No messages should be left on IPC");
        $ipc->useChannelNumber(0);

        $this->assertGreaterThanOrEqual(8, count($mockWriter->events), "At least 8 messages should have been transferred from one channel to another");

        $counter = 0;
        $foundEvents = [];
        foreach ($mockWriter->events as $event) {
            if (isset($messages[$event['priorityName']]) && $event['message'] === $messages[$event['priorityName']]) {
                $counter++;
                $foundEvents[] = $event['message'] . ':' . $event['priorityName'];
            }
        }

        $this->assertEquals(8, $counter, "All messages should have been transferred from one channel to another");
        $this->assertEquals(8, count(array_unique($foundEvents)), "Messages should be unique");
    }

    public function testProcessCreationOnStartup()
    {
        $scheduler = $this->getScheduler(1);

        $amountOfScheduledProcesses = 0;
        $processesCreated = [];
        $processesInitialized = [];

        $em = $scheduler->getEventManager();
        $em->attach(EventsInterface::ON_PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(EventsInterface::ON_PROCESS_CREATE,
            function(EventInterface $e) use (&$amountOfScheduledProcesses, &$processesCreated, $em) {
                $amountOfScheduledProcesses++;

                $uid = 100000000 + $amountOfScheduledProcesses;
                $em->trigger(EventsInterface::ON_PROCESS_INIT, null, ['uid' => $uid]);
                $processesCreated[] = $uid;
            }
        );
        $em->attach(EventsInterface::ON_PROCESS_LOOP,
            function(EventInterface $e) use (&$processesInitialized) {
                $processesInitialized[] = $e->getTarget()->getId();

                // kill the processs
                $e->getTarget()->getStatus()->incrementNumberOfFinishedTasks(100);
            }
        );
        $scheduler->startScheduler(new Event());

        $this->assertEquals(8, $amountOfScheduledProcesses, "Scheduler should try to create 8 processes on its startup");
        $this->assertEquals($processesCreated, $processesInitialized, "Scheduler should have initialized all requested processes");
    }

    public function getSchedulerLaunchTypes()
    {
        return [
            [true, 'running in background'],
            [false, 'running in foreground'],
        ];
    }

    /**
     * @dataProvider getSchedulerLaunchTypes
     */
    public function testSchedulerStartingEvents($runInBackground, $launchDescription)
    {
        $serverStarted = false;
        $schedulerStarted = false;
        $scheduler = $this->getScheduler(1);
        $events = $scheduler->getEventManager();

        $events->attach(EventsInterface::ON_SERVER_START, function() use (& $serverStarted) {
            $serverStarted = true;
        });

        $events->attach(EventsInterface::ON_SCHEDULER_START, function() use (& $schedulerStarted) {
            $schedulerStarted = true;
        });

        $scheduler->start($runInBackground);
        $this->assertTrue($serverStarted, 'Server should have been started when ' . $launchDescription);
        $this->assertTrue($serverStarted, 'Scheduler should have been started when ' . $launchDescription);
    }

    public function testProcessErrorHandling()
    {
        $scheduler = $this->getScheduler(1);

        $amountOfScheduledProcesses = 0;
        $processesCreated = [];
        $processesInitialized = [];

        $em = $scheduler->getEventManager();
        $em->attach(EventsInterface::ON_PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(EventsInterface::ON_PROCESS_CREATE,
            function(EventInterface $e) use (&$amountOfScheduledProcesses, &$processesCreated, $em) {
                $amountOfScheduledProcesses++;

                $uid = 100000000 + $amountOfScheduledProcesses;
                $em->trigger(EventsInterface::ON_PROCESS_INIT, null, ['uid' => $uid]);
                $processesCreated[] = $uid;
            }
        );
        $em->attach(EventsInterface::ON_PROCESS_LOOP,
            function(EventInterface $e) use (&$processesInitialized) {
                $id = $e->getTarget()->getId();
                if (in_array($id, $processesInitialized)) {
                    $e->getTarget()->setBusy();
                    $e->getTarget()->getStatus()->incrementNumberOfFinishedTasks(100);
                    $e->getTarget()->setIdle();
                    return;
                }
                $processesInitialized[] = $id;

                $e->getTarget()->setBusy();
                throw new \RuntimeException("Exception thrown by $id!", 10000);
            }
        );

        $logger = $scheduler->getLogger();
        $mockWriter = new Mock();
        $scheduler->setLogger($logger);
        $scheduler->getLogger()->addWriter($mockWriter);
        $scheduler->startScheduler(new Event());

        $this->assertEquals(8, $amountOfScheduledProcesses, "Scheduler should try to create 8 processes on its startup");
        $this->assertEquals($processesCreated, $processesInitialized, "Scheduler should have initialized all requested processes");

        $foundExceptions = [];
        foreach ($mockWriter->events as $event) {
            if (preg_match('~^Exception \(10000\): Exception thrown by ([0-9]+)~', $event['message'], $matches)) {
                $foundExceptions[] = $matches[1];
            }
        }

        $this->assertEquals(8, count($foundExceptions), "Logger should have reported 8 errors");
    }
}