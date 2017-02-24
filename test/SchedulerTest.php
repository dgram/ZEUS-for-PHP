<?php

namespace ZeusTest;

use PHPUnit_Framework_TestCase;
use Zend\EventManager\Event;
use Zend\EventManager\EventInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Mock;
use Zend\ServiceManager\ServiceManager;
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
        chdir(__DIR__);
    }

    public function tearDown()
    {
        parent::tearDown();
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
}