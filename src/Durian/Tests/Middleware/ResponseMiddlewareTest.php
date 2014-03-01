<?php

namespace Durian\Tests\Middleware;

use Durian\Application;
use Durian\Context;
use Durian\Middleware\ResponseMiddleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * ResponseMiddlewareTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ResponseMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testMissingResponse()
    {
        $app = new Application();
        $app->context(new Context());
        $app->handlers([
            new ResponseMiddleware(),
            function () {
                return 'foo';
            }
        ]);
        $response = $app->handle(new Request());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $response);
        $this->assertSame('foo', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testExceptionResponse()
    {
        $app = new Application();
        $app->context(new Context());
        $app->handlers([
            new ResponseMiddleware(),
            function () {
                throw new \Exception('foo');
            }
        ]);
        $response = $app->handle(new Request());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $response);
        $this->assertSame('foo', $response->getContent());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testHttpExceptionResponse()
    {
        $app = new Application();
        $app->context(new Context());
        $app->handlers([
            new ResponseMiddleware(),
            function () {
                $this->error('I\'m a teapot', 418, ['X-Temperature-Celsius' => 50]);
            }
        ]);
        $response = $app->handle(new Request());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $response);
        $this->assertSame('I\'m a teapot', $response->getContent());
        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(50, $response->headers->get('X-Temperature-Celsius'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDebugResponse()
    {
        $app = new Application();
        $app->context(new Context());
        $app['debug'] = true;
        $app->handlers([
            new ResponseMiddleware(),
            function () {
                throw new \RuntimeException();
            }
        ]);

        $response = $app->handle(new Request());
    }
}
