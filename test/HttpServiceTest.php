<?php

namespace ZeusTest;

use PHPUnit_Framework_TestCase;
use Zeus\ServerService\Http\Service;
use ZeusTest\Helpers\ZeusFactories;

class HttpServiceTest extends PHPUnit_Framework_TestCase
{
    use ZeusFactories;

    /**
     * @return Service
     */
    protected function getService()
    {
        $sm = $this->getServiceManager();
        $scheduler = $this->getScheduler();
        $logger = $scheduler->getLogger();

        $service = $sm->build(Service::class,
            [
                'scheduler_adapter' => $scheduler,
                'logger_adapter' => $logger,
                'config' =>
                [
                    'service_settings' => [
                    'listen_port' => 7070,
                    'listen_address' => '0.0.0.0',
                    'blocked_file_types' => [
                        'php',
                        'phtml'
                    ]
                ]
            ]
        ]);

        return $service;
    }

    public function testServiceCreation()
    {
        $service = $this->getService();
        $service->start();
    }
}