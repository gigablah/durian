<?php

namespace Durian\Tests;

use Durian\Route;

/**
 * RouteTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class RouteTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $route = new Route();

        $this->assertSame([], $route->dump());
    }

    public function testRootRoute()
    {
        $route = new Route();
        $route->route('/');

        $this->assertSame(['/' => ['GET' => []]], $route->dump());
    }

    public function testRouting()
    {
        $A = $B = $C = $D = $E = $F = $G = $H = $I = $J = true;
        $route = new Route('/', [$A]);
        $route->get($B, $C);
        $route->post($D);
        $segment1 = $route->route('/foo', $E, $F);
        $segment1->put($G);
        $segment1->route('/{id:0-9+}')->get($H);
        $segment2 = $route->route('/bar');
        $segment2->delete($I, $J);
        $segment2->patch();

        $this->assertSame([
            '/' => [
                'GET' => [$A, $B, $C],
                'POST' => [$A, $D]
            ],
            '/foo' => [
                'GET' => [$A, $E, $F],
                'PUT' => [$A, $E, $F, $G]
            ],
            '/foo/{id:0-9+}' => [
                'GET' => [$A, $E, $F, $H]
            ],
            '/bar' => [
                'GET' => [$A],
                'DELETE' => [$A, $I, $J],
                'PATCH' => [$A]
            ]
        ], $route->dump());
    }

    public function testMethod()
    {
        $A = $B = $C = $D = $E = true;
        $route = new Route('/', [$A]);
        $route->method('GET', [$B]);
        $route->method('POsT|put||', $C, $D);
        $route->method('foo');
        $route->method('*', $E);

        $this->assertSame([
            '/' => [
                'GET' => [$A, $E, $B],
                'POST' => [$A, $E, $C, $D],
                'PUT' => [$A, $E, $C, $D],
                'FOO' => [$A, $E]
            ]
        ], $route->dump());
    }

    public function testAllMethods()
    {
        $A = $B = $C = $D = $E = $F = $G = $H = $I = true;
        $route = new Route('/', [$A]);
        $route->get($B, $C)
            ->post($C, $D)
            ->put($D, $E)
            ->delete($E, $F)
            ->patch($F, $G)
            ->options($G, $H)
            ->head($H, $I);

        $this->assertSame([
            '/' => [
                'GET' => [$A, $B, $C],
                'POST' => [$A, $C, $D],
                'PUT' => [$A, $D, $E],
                'DELETE' => [$A, $E, $F],
                'PATCH' => [$A, $F, $G],
                'OPTIONS' => [$A, $G, $H],
                'HEAD' => [$A, $H, $I]
            ]
        ], $route->dump());
    }
}
