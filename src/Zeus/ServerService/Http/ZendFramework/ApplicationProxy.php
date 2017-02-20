<?php

namespace Zeus\ServerService\Http\ZendFramework;

use Zend\Mvc\Application;
use Zend\Mvc\ApplicationInterface;
use Zend\Stdlib\RequestInterface;
use Zend\Stdlib\ResponseInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc;
use Zend\Mvc\Service;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Console\Console;

/**
 * Class ApplicationProxy
 * @package Zeus\ServerService\Http\ZendFramework
 * @internal
 * @deprecated
 */
final class ApplicationProxy implements ApplicationInterface
{
    const ERROR_CONTROLLER_CANNOT_DISPATCH = 'error-controller-cannot-dispatch';
    const ERROR_CONTROLLER_NOT_FOUND       = 'error-controller-not-found';
    const ERROR_CONTROLLER_INVALID         = 'error-controller-invalid';
    const ERROR_EXCEPTION                  = 'error-exception';
    const ERROR_ROUTER_NO_MATCH            = 'error-router-no-match';

    /** @var Application */
    protected $application;

    /** @var ResponseInterface */
    protected $response;

    /** @var RequestInterface */
    protected $request;

    /** @var EventManagerInterface */
    protected $eventManager;

    /** @var ServiceLocatorInterface */
    protected $serviceManager;

    /**
     * ApplicationProxy constructor.
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->response = $application->getResponse();
    }

    /**
     * @param string $method
     * @param mixed[] $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->application, $method], $args);
    }

    /**
     * @param ResponseInterface $response
     * @return $this
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get the response object
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the locator object
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceManager()
    {
        if (!$this->serviceManager) {
            $this->serviceManager = clone $this->application->getServiceManager();
        }

        return $this->serviceManager;
    }

    /**
     * @param ServiceLocatorInterface $serviceManager
     * @return $this
     */
    public function setServiceManager(ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }

    /**
     * Get the request object
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param RequestInterface $request
     * @return $this
     */
    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Run the application
     * @return Application|ApplicationInterface
     */
    public function run()
    {
        // Run the application!
        $result = $this->application->run();

        return $result;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->eventManager) {
            $this->eventManager = $this->application->getEventManager();
        }
        return $this->eventManager;
    }

    /**
     * Set the event manager
     *
     * @param EventManagerInterface $eventManager
     * @return $this
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * @param mixed[] $configuration
     * @return $this
     */
    public static function init($configuration = [])
    {
        // Prepare the service manager
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : [];
        $smConfig = new Service\ServiceManagerConfig($smConfig);

        $serviceManager = new ServiceManager();
        $smConfig->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $configuration);

        // Load modules
        $serviceManager->get('ModuleManager')->loadModules();

        $listenersFromAppConfig     = isset($configuration['listeners']) ? $configuration['listeners'] : [];
        $config                     = $serviceManager->get('config');
        $listenersFromConfigService = isset($config['listeners']) ? $config['listeners'] : [];

        $listeners = array_unique(array_merge($listenersFromConfigService, $listenersFromAppConfig));

        $application = $serviceManager->get('Application')->bootstrap($listeners);

        return $application;
    }
}