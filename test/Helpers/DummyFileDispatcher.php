<?php

namespace ZeusTest\Helpers;

use Zend\Http\Response;
use Zeus\ServerService\Http\Dispatcher\DispatcherInterface;
use Zend\Http\Request;

class DummyFileDispatcher implements DispatcherInterface
{
    /** @var bool */
    protected $dispatched = false;

    public function dispatch(Request $request)
    {
        $this->dispatched = true;

        return new Response();
    }

    /**
     * DispatcherInterface constructor.
     * @param mixed[] $config
     * @param DispatcherInterface|null $anotherDispatcher
     */
    public function __construct(array $config, DispatcherInterface $anotherDispatcher = null)
    {

    }

    /**
     * @return bool
     */
    public function isDispatchPerformed()
    {
        return $this->dispatched;
    }
}