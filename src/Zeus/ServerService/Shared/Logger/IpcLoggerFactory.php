<?php

namespace Zeus\ServerService\Shared\Logger;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\Console\Console;
use Zend\Log\Logger;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;

class IpcLoggerFactory implements FactoryInterface
{

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $logger = new Logger;
        $formatter = new ConsoleLogFormatter(Console::getInstance());
        $writer = new IpcLogWriter($options['ipc_adapter']);

        //$writer->addFilter(new Priority(Logger::WARN));
        $writer->setIpcAdapter($options['ipc_adapter']);

        $logProcessor = new ExtraLogProcessor();

        if (is_callable([$logProcessor, 'setConfig'])) {
            $logProcessor->setConfig(['service_name' => $options['service_name']]);
        }

        $logger->addWriter($writer);
        $logger->addProcessor($logProcessor);
        $writer->setFormatter($formatter);

        return $logger;
    }
}