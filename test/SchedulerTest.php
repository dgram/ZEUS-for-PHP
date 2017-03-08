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
use Zeus\Kernel\ProcessManager\Exception\ProcessManagerException;
use Zeus\Kernel\ProcessManager\Scheduler;
use Zeus\Kernel\ProcessManager\SchedulerEvent;
use Zeus\ServerService\Shared\Logger\ConsoleLogFormatter;
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
<<<<<<< HEAD
        $events->attach(SchedulerEvent::EVENT_SCHEDULER_LOOP, function(EventInterface $e) use (&$counter) {
=======
        $events->attach(SchedulerEvent::SCHEDULER_LOOP, function(EventInterface $e) use (&$counter) {
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
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
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::EVENT_PROCESS_CREATE,
            function(EventInterface $e) use ($em) {
                $e->stopPropagation(true);
                $em->trigger(SchedulerEvent::EVENT_PROCESS_CREATED, null, []);
            }
        );
        $em->attach(SchedulerEvent::EVENT_PROCESS_CREATED,
=======
        $em->attach(SchedulerEvent::PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::PROCESS_CREATE,
            function(EventInterface $e) use ($em) {
                $e->stopPropagation(true);
                $em->trigger(SchedulerEvent::PROCESS_CREATED, null, []);
            }
        );
        $em->attach(SchedulerEvent::PROCESS_CREATED,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) use (&$amountOfScheduledProcesses, &$processesCreated, $em) {
                $amountOfScheduledProcesses++;

                $uid = 100000000 + $amountOfScheduledProcesses;
                $event = new SchedulerEvent();
<<<<<<< HEAD
                $event->setName(SchedulerEvent::EVENT_PROCESS_INIT);
=======
                $event->setName(SchedulerEvent::PROCESS_INIT);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
                $event->setParams(['uid' => $uid]);
                $em->triggerEvent($event);
                $processesCreated[] = $uid;
            }
        );
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_LOOP,
=======
        $em->attach(SchedulerEvent::PROCESS_LOOP,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(SchedulerEvent $e) use (&$processesInitialized) {
                $processesInitialized[] = $e->getProcess()->getId();

                // kill the processs
                $e->getProcess()->getStatus()->incrementNumberOfFinishedTasks(100);
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

<<<<<<< HEAD
        $events->attach(SchedulerEvent::INTERNAL_EVENT_KERNEL_START, function() use (& $serverStarted) {
            $serverStarted = true;
        });

        $events->attach(SchedulerEvent::EVENT_SCHEDULER_START, function() use (& $schedulerStarted) {
=======
        $events->attach(SchedulerEvent::SERVER_START, function() use (& $serverStarted) {
            $serverStarted = true;
        });

        $events->attach(SchedulerEvent::SCHEDULER_START, function() use (& $schedulerStarted) {
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
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
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::EVENT_PROCESS_CREATE,
            function(EventInterface $e) use ($em) {
                $e->stopPropagation(true);
                $em->trigger(SchedulerEvent::EVENT_PROCESS_CREATED, null, []);
            }
        );
        $em->attach(SchedulerEvent::EVENT_PROCESS_CREATED,
=======
        $em->attach(SchedulerEvent::PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::PROCESS_CREATE,
            function(EventInterface $e) use ($em) {
                $e->stopPropagation(true);
                $em->trigger(SchedulerEvent::PROCESS_CREATED, null, []);
            }
        );
        $em->attach(SchedulerEvent::PROCESS_CREATED,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) use (&$amountOfScheduledProcesses, &$processesCreated, $em) {
                $amountOfScheduledProcesses++;
                $e->stopPropagation(true);
                $uid = 100000000 + $amountOfScheduledProcesses;
                $event = new SchedulerEvent();
<<<<<<< HEAD
                $event->setName(SchedulerEvent::EVENT_PROCESS_INIT);
=======
                $event->setName(SchedulerEvent::PROCESS_INIT);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
                $event->setParams(['uid' => $uid]);
                $em->triggerEvent($event);
                $processesCreated[] = $uid;
            }
        );
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_LOOP,
=======
        $em->attach(SchedulerEvent::PROCESS_LOOP,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(SchedulerEvent $e) use (&$processesInitialized) {
                $id = $e->getProcess()->getId();
                if (in_array($id, $processesInitialized)) {
                    $e->getProcess()->setRunning();
                    $e->getProcess()->getStatus()->incrementNumberOfFinishedTasks(100);
                    $e->getProcess()->setWaiting();
                    return;
                }
                $processesInitialized[] = $id;

                $e->getProcess()->setRunning();
                throw new \RuntimeException("Exception thrown by $id!", 10000);
            }
        );

        $logger = $scheduler->getLogger();
        $mockWriter = new Mock();
        $scheduler->setLogger($logger);
        $scheduler->getLogger()->addWriter($mockWriter);
        $mockWriter->setFormatter(new ConsoleLogFormatter(Console::getInstance()));
        $scheduler->startScheduler(new SchedulerEvent());

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

    public function testProcessShutdownSequence()
    {
        $scheduler = $this->getScheduler(1);

        $amountOfScheduledProcesses = 0;
        $processesCreated = [];

        $em = $scheduler->getEventManager();
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::EVENT_PROCESS_CREATE,
=======
        $em->attach(SchedulerEvent::PROCESS_EXIT, function(EventInterface $e) {$e->stopPropagation(true);});
        $em->attach(SchedulerEvent::PROCESS_CREATE,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) use (&$amountOfScheduledProcesses, &$processesCreated, $em) {
                $amountOfScheduledProcesses++;

                $uid = 100000000 + $amountOfScheduledProcesses;
                $processesCreated[$uid] = true;
<<<<<<< HEAD
                $em->trigger(SchedulerEvent::EVENT_PROCESS_CREATED, null, ['uid' => $uid]);
            }
        );
        $em->attach(SchedulerEvent::EVENT_PROCESS_LOOP,
=======
                $em->trigger(SchedulerEvent::PROCESS_CREATED, null, ['uid' => $uid]);
            }
        );
        $em->attach(SchedulerEvent::PROCESS_LOOP,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) {
                // stop the process
                $e->getTarget()->getStatus()->incrementNumberOfFinishedTasks(100);
            }
        );

        $schedulerStopped = false;
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_SCHEDULER_STOP,
=======
        $em->attach(SchedulerEvent::SCHEDULER_STOP,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) use (&$schedulerStopped) {
                $schedulerStopped = true;
                $e->stopPropagation(true);
            }, -9999);

        $unknownProcesses = [];
<<<<<<< HEAD
        $em->attach(SchedulerEvent::EVENT_PROCESS_TERMINATE,
            function(EventInterface $e) use ($em) {
                $uid = $e->getParam('uid');
                $em->trigger(SchedulerEvent::EVENT_PROCESS_TERMINATED, null, ['uid' => $uid]);
            }
        );

        $em->attach(SchedulerEvent::EVENT_PROCESS_TERMINATED,
=======
        $em->attach(SchedulerEvent::PROCESS_TERMINATE,
            function(EventInterface $e) use ($em) {
                $uid = $e->getParam('uid');
                $em->trigger(SchedulerEvent::PROCESS_TERMINATED, null, ['uid' => $uid]);
            }
        );

        $em->attach(SchedulerEvent::PROCESS_TERMINATED,
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26
            function(EventInterface $e) use (&$unknownProcesses, &$processesCreated, $em) {
                $uid = $e->getParam('uid');
                if (!isset($processesCreated[$uid])) {
                    $unknownProcesses[] = true;
                } else {
                    unset($processesCreated[$uid]);
                }
            }
        );

        $scheduler->startScheduler(new Event());

        $this->assertEquals(8, $amountOfScheduledProcesses, "Scheduler should try to create 8 processes on its startup");

<<<<<<< HEAD
        $scheduler->getEventManager()->trigger(SchedulerEvent::EVENT_SCHEDULER_STOP, null);
=======
        $scheduler->getEventManager()->trigger(SchedulerEvent::SCHEDULER_STOP, null);
>>>>>>> 62bb26e12691695d3208bff4dc2497dcae70eb26

        $this->assertEquals(0, count($processesCreated), 'All processes should have been planned to be terminated on scheduler shutdown');
        $this->assertEquals(0, count($unknownProcesses), 'No unknown processes should have been terminated');
    }
}