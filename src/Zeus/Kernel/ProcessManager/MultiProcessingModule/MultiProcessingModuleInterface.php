<?php

namespace Zeus\Kernel\ProcessManager\MultiProcessingModule;

use Zend\EventManager\EventManagerInterface;

interface MultiProcessingModuleInterface
{
    public function __construct();

    /**
     * @param EventManagerInterface $events
     * @return mixed
     */
    public function attach(EventManagerInterface $events);

    /**
     * @return MultiProcessingModuleCapabilities
     */
    public function getCapabilities();
}