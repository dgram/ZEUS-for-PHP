<?php

namespace ZeusTest;

use PHPUnit_Framework_TestCase;
use Zeus\Kernel\IpcServer\Adapter\ApcAdapter;
use Zeus\Kernel\IpcServer\Adapter\FifoAdapter;
use Zeus\Kernel\IpcServer\Adapter\IpcAdapterInterface;
use Zeus\Kernel\IpcServer\Adapter\MsgAdapter;
use Zeus\Kernel\IpcServer\Adapter\SocketAdapter;
use ZeusTest\Helpers\ZeusFactories;

class IpcTest extends PHPUnit_Framework_TestCase
{
    use ZeusFactories;

    public function getIpcAdapters()
    {
        return [
            [FifoAdapter::class],
            [SocketAdapter::class],
            [MsgAdapter::class],
            [ApcAdapter::class],
        ];
    }

    /**
     * @dataProvider getIpcAdapters
     * @param string $adapter
     */
    public function testIpcAdapters($adapter)
    {
        $messagesAmount = 100;

        $sm = $this->getServiceManager();
        /** @var IpcAdapterInterface $ipcAdapter */
        $ipcAdapter = $sm->build(IpcAdapterInterface::class, ['ipc_adapter' => $adapter, 'service_name' => 'zeus-test-' . md5($adapter)]);

        $this->assertInstanceOf($adapter, $ipcAdapter);

        if (!$ipcAdapter::isSupported()) {
            $this->markTestSkipped('The PHP configuration or OS system does not support ' . get_class($ipcAdapter));
        }

        $ipcAdapter->useChannelNumber(0);
        $this->assertEquals(0, count($ipcAdapter->receiveAll()), 'Input queue should be empty');

        $ipcAdapter->useChannelNumber(1);
        $this->assertEquals(0, count($ipcAdapter->receiveAll()), 'Output queue should be empty');

        $ipcAdapter->useChannelNumber(0);
        foreach (range(1, $messagesAmount) as $index) {
            $ipcAdapter->send('Message number ' . $index);
        }

        $this->assertEquals(0, count($ipcAdapter->receiveAll()), $adapter . ' input queue should be empty after sending some data');

        $ipcAdapter->useChannelNumber(1);
        $output = $ipcAdapter->receiveAll();
        $this->assertEquals($messagesAmount, count($output), 'Output queue should contain all the messages');
        $this->assertEquals(0, count($ipcAdapter->receiveAll()), 'Output queue should be empty after fetching the data');

        foreach (range(1, $messagesAmount) as $index) {
            $message = 'Message number ' . $index;

            $this->assertContains($message, $output, $message . ' should have been returned as output');
        }

        $ipcAdapter->disconnect();
    }
}