<?php

namespace Zeus\ServerService\Http\Dispatcher;

use Zend\Http\Request;
use Zend\Http\Response;

interface DispatcherInterface
{
    /**
     * DispatcherInterface constructor.
     * @param mixed[] $config
     * @param DispatcherInterface|null $anotherDispatcher
     */
    public function __construct(array $config, DispatcherInterface $anotherDispatcher = null);

    /**
     * @param Request $httpRequest
     * @return Response
     */
    public function dispatch(Request $httpRequest);
}