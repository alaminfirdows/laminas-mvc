<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc;

use Laminas\Mvc\SendResponseListener;
use PHPUnit_Framework_TestCase as TestCase;

class SendResponseListenerTest extends TestCase
{

    public function testEventManagerIdentifiers()
    {
        $listener = new SendResponseListener();
        $identifiers = $listener->getEventManager()->getIdentifiers();
        $expected    = array('Laminas\Mvc\SendResponseListener');
        $this->assertEquals($expected, array_values($identifiers));
    }

    public function testSendResponseTriggersSendResponseEvent()
    {
        $listener = new SendResponseListener();
        $result = array();
        $listener->getEventManager()->attach('sendResponse', function ($e) use (&$result) {
            $result['target'] = $e->getTarget();
            $result['response'] = $e->getResponse();
        }, 10000);
        $mockResponse = $this->getMockForAbstractClass('Laminas\Stdlib\ResponseInterface');
        $mockMvcEvent = $this->getMock('Laminas\Mvc\MvcEvent', $methods = array('getResponse'));
        $mockMvcEvent->expects($this->any())->method('getResponse')->will($this->returnValue($mockResponse));
        $listener->sendResponse($mockMvcEvent);
        $expected = array(
            'target' => $listener,
            'response' => $mockResponse
        );
        $this->assertEquals($expected, $result);
    }
}
