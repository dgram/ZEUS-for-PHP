<?php

namespace Zeus\Kernel\IpcServer\Factory;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zeus\Kernel\IpcServer\Adapter\FifoAdapter;
use Zeus\Kernel\IpcServer\Adapter\IpcAdapterInterface;

final class IpcServerFactory implements FactoryInterface
{
    /** @var IpcAdapterInterface[] */
    protected static $channels = [];

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
        $channelName = $options['service_name'];

        $ipcAdapter = isset($options['ipc_adapter']) ? $options['ipc_adapter'] : FifoAdapter::class;

        if (!isset(self::$channels[$channelName])) {
            self::$channels[$channelName] = $container->build($ipcAdapter, $options);
        }

        return self::$channels[$channelName];
    }
}