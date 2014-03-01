<?php

namespace Durian\Tests;

use Durian\Handler;
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

        $this->assertNull(call_user_func($handler));
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

    public function testBindToWithClosure()
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
        $app = $this->getMock('Durian\\Application');
        $app->expects($this->any())
            ->method('context')
            ->will($this->returnValue($context));

        $handler->bindTo($app);

        $this->assertSame($request, call_user_func($handler));
    }

    public function testBindToWithMiddleware()
    {
        $middleware = $this->getMock('Durian\\Middleware\\AbstractMiddleware');
        $middleware->expects($this->once())
            ->method('bindTo')
            ->with($this->isInstanceOf('Durian\\Application'));
        $handler = new Handler($middleware);
        $app = $this->getMock('Durian\\Application');

        $handler->bindTo($app);
    }
}
