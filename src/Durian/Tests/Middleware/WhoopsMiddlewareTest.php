<?php

namespace Durian\Tests\Middleware;

use Durian\Application;
use Durian\Context;
use Durian\Middleware\WhoopsMiddleware;
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
        $app = new Application;
        $app->context(new Context());
        $app->handlers([
            new WhoopsMiddleware($app),
            function () {
                throw new \Exception('foo');
            }
        ]);
        $app['debug'] = true;
        $response = $app->run();

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $response);
        $this->assertSame(500, $response->getStatusCode());
    }
}
