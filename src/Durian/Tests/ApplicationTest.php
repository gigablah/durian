<?php

namespace Durian\Tests;

use Durian\Application;

/**
 * ApplicationTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $app = new Application();

        $this->assertSame($app['debug'], false);
        $this->assertSame($app['durian.handler_class'], 'Durian\\Handler');
        $this->assertSame($app['durian.route_class'], 'Durian\\Route');
        $this->assertSame($app['durian.context_class'], 'Durian\\Context');

        $app = new Application([
            'debug' => true,
            'locale' => 'en_SG'
        ]);

        $this->assertSame($app['debug'], true);
        $this->assertSame($app['locale'], 'en_SG');
    }

    public function testHandlerWithCallable()
    {
        $app = new Application();
        $handler = $app->handler(function () {}, function () {});

        $this->assertInstanceOf('Durian\\Handler', $handler);
    }

    public function testHandlerWithHandler()
    {
        $app = new Application();
        $mock = $this->getMock('Durian\\Handler');
        $handler = $app->handler($mock);

        $this->assertSame($mock, $handler);
    }

    public function testHandlerWithService()
    {
        $app = new Application();
        $mock = $this->getMock('Durian\\Handler');
        $app['handler.test'] = $mock;
        $handler = $app->handler('handler.test');

        $this->assertSame($mock, $handler);
    }

    public function testReplaceHandlers()
    {
        $app = new Application();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler2 = $this->getMock('Durian\\Handler');
        $app->handlers([$handler1]);

        $this->assertSame([$handler1], $app->handlers());

        $app->handlers([$handler2]);

        $this->assertSame([$handler2], $app->handlers());
    }

    public function testAppendHandlers()
    {
        $app = new Application();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler2 = $this->getMock('Durian\\Handler');
        $app->handlers([$handler1]);
        $app->handlers([$handler2], false);

        $this->assertSame([$handler1, $handler2], $app->handlers());
    }

    public function testBefore()
    {
        $app = new Application();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler2 = $this->getMock('Durian\\Handler');
        $app->handlers([$handler1]);
        $app->before($handler2);

        $this->assertSame([$handler2, $handler1], $app->handlers());
    }

    public function testAfter()
    {
        $app = new Application();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler2 = $this->getMock('Durian\\Handler');
        $app->handlers([$handler1]);
        $app->after($handler2);

        $this->assertSame([$handler1, $handler2], $app->handlers());
    }

    public function testRoute()
    {
        $app = new Application();
        $route = $app->route('/');

        $this->assertInstanceOf($app['durian.route_class'], $route);

        $route = $app->route('/', function () {});

        $this->assertInstanceOf($app['durian.route_class'], $route);

        $route = $app->route('/', function () {}, function () {});

        $this->assertInstanceOf($app['durian.route_class'], $route);
    }

    public function testReplaceRoutes()
    {
        $app = new Application();
        $route1 = $this->getMock('Durian\\Route');
        $route2 = $this->getMock('Durian\\Route');
        $app->routes([$route1]);

        $this->assertSame([$route1], $app->routes());

        $app->routes([$route2]);

        $this->assertSame([$route2], $app->routes());
    }

    public function testAppendRoutes()
    {
        $app = new Application();
        $route1 = $this->getMock('Durian\\Route');
        $route2 = $this->getMock('Durian\\Route');
        $app->routes([$route1]);
        $app->routes([$route2], false);

        $this->assertSame([$route1, $route2], $app->routes());
    }

    public function testContext()
    {
        $app = new Application();
        $reflectionClass = new \ReflectionClass($app);
        $contextProperty = $reflectionClass->getProperty('context');
        $contextProperty->setAccessible(true);
        $context1 = $this->getMock('Durian\\Context');
        $contextProperty->setValue($app, [$context1]);

        $this->assertSame($context1, $app->context());

        $context2 = $this->getMock('Durian\\Context');
        $contextProperty->setValue($app, [$context1, $context2]);

        $this->assertSame($context2, $app->context());
    }

    public function testRunWithEmptyMiddleware()
    {
        $app = new Application();
        $app->handlers([]);
        $app['durian.send_response'] = false;
        $result = $app->run();

        $this->assertNull($result);
    }

    public function testRunWithSubRequest()
    {
        $app = new Application();
        $context = $this->getMock('Durian\\Context');
        $app->context($context);
        $result = $app->run();

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $result);
    }

    public function testRunWithMethodAndPath()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->at(0))
            ->method('__invoke')
            ->with($this->equalTo('FOO'));
        $probe->expects($this->at(1))
            ->method('__invoke')
            ->with($this->equalTo('/bar'));
        $app->handlers([
            $app->handler(function () use ($probe) {
                $probe->__invoke($this->request()->getMethod());
                $probe->__invoke($this->request()->getPathInfo());
            })
        ]);
        $app['durian.send_response'] = false;

        $app->run('FOO', '/bar');
    }

    public function testRunWithDefaultMiddleware()
    {
        $app = new Application();
        $app['durian.send_response'] = false;
        $result = $app->run();

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $result);
    }

    public function testRunWithHandler()
    {
        $app = new Application();
        $app->handlers([
            $app->handler(function () {
                $this->response('');
            })
        ]);
        $result = $app->run();

        $this->assertNull($result);
    }

    public function testRunWithGenerator()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->at(0))
            ->method('__invoke');
        $probe->expects($this->at(1))
            ->method('bindTo')
            ->with($this->isInstanceOf('Durian\\Application'));
        $probe->expects($this->at(2))
            ->method('__invoke');
        $callback = function () use ($probe) {
            $probe->__invoke();
            yield;
            $probe->__invoke();
        };
        $generator = $callback();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler1->expects($this->once())
            ->method('bindTo')
            ->with($this->isInstanceOf('Durian\\Application'));
        $handler1->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($generator));
        $handler2 = function () use ($probe, $app) {
            $probe->bindTo($app);
        };
        $app->handlers([$handler1, $handler2]);
        $app['durian.send_response'] = false;

        $app->run();
    }

    public function testRunWithHandlerHandler()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->at(0))
            ->method('__invoke')
            ->with($this->equalTo(1));
        $probe->expects($this->at(1))
            ->method('__invoke')
            ->with($this->equalTo(2));
        $app->handlers([
            $app->handler(function () use ($probe, $app) {
                $probe->__invoke(1);
                return $app->handler(function () use ($probe) {
                    $probe->__invoke(2);
                });
            })
        ]);
        $app['durian.send_response'] = false;

        $app->run();
    }

    public function testRunWithHandlerGenerator()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->at(0))
            ->method('__invoke')
            ->with($this->equalTo(1));
        $probe->expects($this->at(1))
            ->method('__invoke')
            ->with($this->equalTo(2));
        $probe->expects($this->at(2))
            ->method('__invoke')
            ->with($this->equalTo(3));
        $app->handlers([
            $app->handler(function () use ($probe, $app) {
                $probe->__invoke(1);
                foreach (range(2, 3) as $input) {
                    yield $app->handler(function () use ($probe, $input) {
                        $probe->__invoke($input);
                    });
                }
            })
        ]);
        $app['durian.send_response'] = false;

        $app->run();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRunWithoutExceptionHandling()
    {
        $app = new Application();
        $app->handlers([
            $app->handler(function () {
                throw new \RuntimeException();
            })
        ]);
        $app['durian.bubble_errors'] = false;
        $app['durian.send_response'] = false;

        $app->run();
    }

    public function testRunWithExceptionHandling()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->at(0))
            ->method('__invoke');
        $probe->expects($this->at(1))
            ->method('bindTo')
            ->will($this->throwException(new \Exception()));
        $probe->expects($this->at(2))
            ->method('__invoke')
            ->with($this->isInstanceOf('Exception'));
        $probe->expects($this->at(3))
            ->method('__invoke')
            ->with($this->equalTo(true));
        $callback1 = function () use ($probe) {
            yield;
            $probe->__invoke(true);
        };
        $callback2 = function () use ($probe) {
            try {
                yield $probe->__invoke();
            } catch (\Exception $e) {
                $probe->__invoke($e);
            }
        };
        $generator1 = $callback1();
        $generator2 = $callback2();
        $generator3 = $callback1();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler1->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($generator1));
        $handler2 = $this->getMock('Durian\\Handler');
        $handler2->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($generator2));
        $handler3 = $this->getMock('Durian\\Handler');
        $handler3->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($generator3));
        $handler4 = function () use ($probe, $app) {
            $probe->bindTo($app);
        };
        $app->handlers([$handler1, $handler2, $handler3, $handler4]);
        $app['durian.send_response'] = false;

        $app->run();
    }

    /**
     * @expectedException \LogicException
     */
    public function testRunWithUncaughtException()
    {
        $app = new Application();
        $probe = $this->getMock('Durian\\Handler');
        $probe->expects($this->once())
            ->method('bindTo')
            ->will($this->throwException(new \LogicException()));
        $callback = function () use ($probe) {
            try {
                yield;
            } catch (\RuntimeException $e) {
            }
        };
        $generator = $callback();
        $handler1 = $this->getMock('Durian\\Handler');
        $handler1->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($generator));
        $handler2 = function () use ($probe, $app) {
            $probe->bindTo($app);
        };
        $app->handlers([$handler1, $handler2]);
        $app['durian.send_response'] = false;

        $app->run();
    }
}
