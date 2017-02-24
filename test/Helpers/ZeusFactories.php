<?php

namespace ZeusTest\Helpers;

use Zend\EventManager\EventInterface;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;
use Zeus\Kernel\IpcServer\Adapter\IpcAdapterInterface;
use Zeus\Kernel\IpcServer\Factory\IpcAdapterAbstractFactory;
use Zeus\Kernel\IpcServer\Factory\IpcServerFactory;
use Zeus\Kernel\ProcessManager\Factory\ProcessFactory;
use Zeus\Kernel\ProcessManager\Factory\SchedulerFactory;
use Zeus\Kernel\ProcessManager\Process;
use Zeus\Kernel\ProcessManager\Scheduler;
use Zeus\Kernel\ProcessManager\Scheduler\EventsInterface;
use Zeus\ServerService\Shared\Logger\IpcLogWriter;

trait ZeusFactories
{
    /**
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        $sm = new ServiceManager();
        $sm->addAbstractFactory(IpcAdapterAbstractFactory::class);
        $sm->setFactory(Scheduler::class, SchedulerFactory::class);
        $sm->setFactory(Process::class, ProcessFactory::class);
        $sm->setFactory(IpcAdapterInterface::class, IpcServerFactory::class);
        $sm->setFactory(DummyServiceFactory::class, DummyServiceFactory::class);
        $config = require realpath(__DIR__ . "/../../config/module.config.php");

        $config = ArrayUtils::merge($config,
            [
                'zeus_process_manager' => [
                    'schedulers' => [
                        'test_scheduler_1' => [
                            'scheduler_name' => 'test-scheduler',
                            'multiprocessing_module' => DummyServiceFactory::class,
                            'max_processes' => 32,
                            'max_process_tasks' => 100,
                            'min_spare_processes' => 3,
                            'max_spare_processes' => 5,
                            'start_processes' => 8,
                            'enable_process_cache' => true
                        ]
                    ]
                ]
            ]
        );

        $sm->setService('configuration', $config);

        return $sm;
    }

    /**
     * @param int $mainLoopIterantions
     * @return Scheduler
     */
    public function getScheduler($mainLoopIterantions = 0)
    {
        $sm = $this->getServiceManager();

        $ipcAdapter = $sm->build(DummyIpcAdapter::class, ['service_name' => 'test-service']);
        $logger = new Logger();
        $ipcWriter = new IpcLogWriter();
        $ipcWriter->setIpcAdapter($ipcAdapter);
        $logger->addWriter($ipcWriter);

        $scheduler = $sm->build(Scheduler::class, [
            'ipc_adapter' => $ipcAdapter,
            'service_name' => 'test-service',
            'scheduler_name' => 'test-scheduler',
            'service_logger_adapter' => $logger,
            'main_logger_adapter' => $logger,
        ]);

        if ($mainLoopIterantions > 0) {
            $events = $scheduler->getEventManager();
            $events->attach(EventsInterface::ON_SCHEDULER_LOOP, function (EventInterface $e) use (&$mainLoopIterantions) {

                $mainLoopIterantions--;

                if ($mainLoopIterantions === 0) {
                    $e->getTarget()->setContinueMainLoop(false);
                }
            });
        }

        return $scheduler;
    }
}