<?php

namespace Durian\Tests\Middleware;

use Durian\Application;
use Durian\Context;
use Durian\Middleware\RouterMiddleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * RouterMiddlewareTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class RouterMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleRoute()
    {
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new RouterMiddleware()
        ]);
        $app->route('/', function () {
            $this->response('Hello World');
        });
        $response = $app->handle(Request::create('/'));

        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testInvalidPath()
    {
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new RouterMiddleware()
        ]);
        $app->route('/', function () {
            $this->response('Hello World');
        });

        $app->handle(Request::create('/foo'));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    public function testInvalidMethod()
    {
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new RouterMiddleware()
        ]);
        $app->route('/', function () {
            $this->response('Hello World');
        });

        $app->handle(Request::create('/', 'POST'));
    }

    public function testSlugParam()
    {
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new RouterMiddleware()
        ]);
        $app->route('/hello/{name}', function () {
            $this->response($this->param('name'));
        });

        $response = $app->handle(Request::create('/hello/foo'));

        $this->assertSame('foo', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIntegerParam()
    {
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new RouterMiddleware()
        ]);
        $app->route('/blog/{id:[0-9]+}', function () {
            $this->response($this->param('id'));
        });

        $response = $app->handle(Request::create('/blog/12345'));

        $this->assertSame('12345', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }
}
