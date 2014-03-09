<?php

namespace Durian\Tests;

use Durian\Handler;
use Durian\Middleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * HandlerTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class HandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $handler = new Handler();

        $this->assertSame([
            'iterate' => false,
            'catch_errors' => true,
            'terminate_on_response' => true
        ], $handler->options());
        $this->assertNull(call_user_func($handler));
    }

    public function testOptions()
    {
        $handler = new Handler(null, null, ['iterate' => true]);

        $this->assertTrue($handler->options('iterate'));
    }

    public function testInvokeWithBooleanTest()
    {
        $handler = new Handler('foo', true);

        $this->assertSame('foo', call_user_func($handler));

        $handler = new Handler('foo', false);

        $this->assertNull(call_user_func($handler));
    }

    public function testInvokeWithClosureTest()
    {
        $handler = new Handler('foo', function () {
            return true;
        });

        $this->assertSame('foo', call_user_func($handler));

        $handler = new Handler('foo', function () {
            return false;
        });

        $this->assertNull(call_user_func($handler));
    }

    public function testInvokeWithTerminatingResponseTest()
    {
        $handler = new Handler('foo', function () {
            $this->response('bar');
        });
        $context = $this->getMock('Durian\\Context');
        $context->expects($this->at(0))
            ->method('response')
            ->with($this->equalTo('bar'));
        $context->expects($this->at(1))
            ->method('response')
            ->will($this->returnValue(true));
        $handler->context($context);

        $this->assertNull(call_user_func($handler));
    }

    public function testBindContextToClosure()
    {
        $handler = new Handler(function () {
            return $this->request();
        }, function () {
            return $this->master();
        });
        $request = new Request();
        $context = $this->getMock('Durian\\Context');
        $context->expects($this->once())
            ->method('request')
            ->will($this->returnValue($request));
        $context->expects($this->once())
            ->method('master')
            ->will($this->returnValue(true));
        $handler->context($context);

        $this->assertSame($context, $handler->context());
        $this->assertSame($request, call_user_func($handler));
    }

    public function testBindContextToMiddleware()
    {
        $middleware = new MockMiddleware();
        $request = new Request();
        $context = $this->getMock('Durian\\Context');
        $context->expects($this->once())
            ->method('request')
            ->will($this->returnValue($request));
        $middleware->context($context);

        $this->assertSame($request, call_user_func($middleware));
    }
}

class MockMiddleware extends Middleware
{
    public function run()
    {
        return $this->request();
    }
}
