<?php

namespace Durian\Tests\Middleware;

use Durian\Application;
use Durian\Middleware\WhoopsMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * WhoopsMiddlewareTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class WhoopsMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleException()
    {
        if (!class_exists('Whoops\\Run')) {
            $this->markTestSkipped();
        }

        $app = new Application();
        $app->handlers([
            new WhoopsMiddleware($app),
            function () {
                throw new \Exception('foo');
            }
        ]);
        $response = $app->run();

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $response);
        $this->assertSame(500, $response->getStatusCode());
    }

    /**
     * @expectedException \Exception
     */
    public function testHandleExceptionGivenSubRequestShouldThrowException()
    {
        if (!class_exists('Whoops\\Run')) {
            $this->markTestSkipped();
        }

        $app = new Application();
        $app->handlers([
            new WhoopsMiddleware($app),
            function () {
                throw new \Exception('foo');
            }
        ]);
        $app['app.context']->request(new Request(), HttpKernelInterface::SUB_REQUEST);
        $app->run();
    }
}
