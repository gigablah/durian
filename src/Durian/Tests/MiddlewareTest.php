<?php

namespace Durian\Tests;

use Durian\Application;
use Durian\Middleware;
use Durian\ContextTrait;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MiddlewareTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testRequest()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $request1 = new Request();
        $app['app.context']->request($request1);

        $this->assertSame($request1, $middleware->request());

        $request2 = new Request();
        $middleware->request($request2);

        $this->assertSame($request2, $middleware->request());
    }

    public function testResponse()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $response = new Response('foo');
        $app['app.context']->response($response);

        $this->assertSame($response, $middleware->response());
        $this->assertSame('foo', $middleware->response()->getContent());

        $middleware->response('I\'m a teapot', 418, ['X-Temperature-Celsius' => 50]);

        $this->assertSame('I\'m a teapot', $middleware->response()->getContent());
        $this->assertSame(418, $middleware->response()->getStatusCode());
        $this->assertSame(50, $middleware->response()->headers->get('X-Temperature-Celsius'));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function testError()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);

        $middleware->error();
    }

    public function testErrorWithException()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);

        try {
            $middleware->error(new \RuntimeException('foo'));
        } catch (\Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
            $this->assertSame('foo', $e->getMessage());
        }
    }

    public function testErrorWithFullArguments()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);

        try {
            $middleware->error('bar', 403, ['foo' => 'bar'], 42);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\HttpKernel\\Exception\\HttpException', $e);
            $this->assertSame('bar', $e->getMessage());
            $this->assertSame(['foo' => 'bar'], $e->getHeaders());
            $this->assertSame(42, $e->getCode());
        }
    }

    public function testMaster()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $app['app.context']->request(new Request(), HttpKernelInterface::MASTER_REQUEST);

        $this->assertTrue($middleware->master());

        $app['app.context']->request(new Request(), HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($middleware->master());
    }

    public function testParams()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $app['app.context']->params(['foo' => 'bar', 'bar' => 'foo']);

        $this->assertSame('bar', $middleware->params('foo'));
        $this->assertSame('foo', $middleware->params('bar'));
        $this->assertNull($middleware->params('baz'));
        $this->assertSame('foo', $middleware->params('baz', 'foo'));

        $middleware->params(['foo' => 'baz', 'bar' => null]);

        $this->assertSame('baz', $middleware->params('foo'));
        $this->assertNull($middleware->params('bar'));
        $this->assertNull($middleware->params('bar', 'baz'));

        $this->assertSame(['foo' => 'baz', 'bar' => null], $middleware->params());
    }

    public function testLast()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $app['app.context']->last('foo');

        $this->assertSame('foo', $middleware->last());

        $middleware->last('bar');

        $this->assertSame('bar', $middleware->last());
    }

    public function testClear()
    {
        $app = new Application;
        $middleware = new TestMiddleware();
        $middleware->context($app['app.context']);
        $app['app.context']->request(new Request());

        $middleware->clear();

        $this->assertNull($middleware->request());
    }
}

class TestMiddleware extends Middleware
{
    use ContextTrait;
}
