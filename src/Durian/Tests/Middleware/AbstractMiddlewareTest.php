<?php

namespace Durian\Tests\Middleware;

use Durian\Application;
use Durian\Context;
use Durian\Middleware\AbstractMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AbstractMiddlewareTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class AbstractMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testRequest()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $request1 = new Request();
        $app->context(new Context($request1));

        $this->assertSame($request1, $middleware->request());

        $request2 = new Request();
        $middleware->request($request2);

        $this->assertSame($request2, $middleware->request());
    }

    public function testResponse()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $response = new Response('foo');
        $context = new Context();
        $context->response($response);
        $app->context($context);

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
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $app->context(new Context());

        $middleware->error();
    }

    public function testErrorWithException()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $app->context(new Context());

        try {
            $middleware->error(new \RuntimeException('foo'));
        } catch (\Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
            $this->assertSame('foo', $e->getMessage());
        }
    }

    public function testErrorWithFullArguments()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $app->context(new Context());

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
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $app->context(new Context(null, HttpKernelInterface::MASTER_REQUEST));

        $this->assertTrue($middleware->master());

        $app->context(new Context(null, HttpKernelInterface::SUB_REQUEST));

        $this->assertFalse($middleware->master());
    }

    public function testParams()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $context = new Context();
        $context->params(['foo' => 'bar', 'bar' => 'foo']);
        $app->context($context);

        $this->assertSame('bar', $middleware->param('foo'));
        $this->assertSame('foo', $middleware->param('bar'));

        $middleware->params(['foo' => 'baz']);

        $this->assertSame('baz', $middleware->param('foo'));
        $this->assertSame('foo', $middleware->param('bar'));

        $this->assertSame(['foo' => 'baz', 'bar' => 'foo'], $middleware->params());
    }

    public function testAppend()
    {
        $middleware = new TestMiddleware();
        $app = new Application;
        $middleware->bindTo($app);
        $context = new Context();
        $context->append('foo');
        $app->context($context);

        $this->assertSame('foo', $middleware->last());

        $middleware->append('bar');

        $this->assertSame('bar', $middleware->last());
    }
}

class TestMiddleware extends AbstractMiddleware
{
    public function request(Request $request = null)
    {
        return parent::request($request);
    }

    public function response($response = null, $status = 200, array $headers = [])
    {
        return parent::response($response, $status, $headers);
    }

    public function error($exception = '', $status = 500, array $headers = [], $code = 0)
    {
        parent::error($exception, $status, $headers, $code);
    }

    public function master()
    {
        return parent::master();
    }

    public function param($key, $default = null)
    {
        return parent::param($key, $default);
    }

    public function params(array $params = null)
    {
        return parent::params($params);
    }

    public function append($output)
    {
        parent::append($output);
    }

    public function last()
    {
        return parent::last();
    }
}
