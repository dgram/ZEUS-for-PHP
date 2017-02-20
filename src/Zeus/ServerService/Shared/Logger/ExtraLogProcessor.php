<?php

namespace Zeus\ServerService\Shared\Logger;

use Zend\Log\Processor\ProcessorInterface;

class ExtraLogProcessor implements ProcessorInterface
{
    /** @var float */
    protected $launchMicrotime = null;

    /** @var mixed[] */
    protected $config;

    public function __construct()
    {
        $this->config['service_name'] = '<unknown>';
    }

    /**
     * @param mixed[] $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param mixed[] $event
     * @return mixed[]
     */
    public function process(array $event)
    {
        if (!isset($event['extra'])) {
            $event['extra'] = [];
        }

        $event['extra']['service_name'] = isset($event['extra']['service_name']) ? $event['extra']['service_name'] : $this->config['service_name'];
        $event['extra']['uid'] = isset($event['extra']['uid']) ? $event['extra']['uid'] : getmypid();
        $event['extra']['logger'] = isset($event['extra']['logger']) ? $event['extra']['logger'] : '<unknown>';
        $microtime = microtime(true);

        $microtime = $microtime > 1 ? $microtime - floor($microtime) : $microtime;
        $event['extra']['microtime'] = isset($event['extra']['microtime']) ? $event['extra']['microtime'] : (int) ($microtime * 1000);

        return $event;
    }
}