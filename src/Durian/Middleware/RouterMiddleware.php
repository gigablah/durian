<?php

namespace Durian\Middleware;

use Durian\Handler;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Route dispatcher.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class RouterMiddleware extends AbstractMiddleware
{
    /**
     * Compile all the routes and match the resulting regex against the current request.
     *
     * If a match is found, yield each handler associated with the route.
     *
     * @yields Handler Handler associated with the matched route
     *
     * @throws NotFoundHttpException if no matching route is found
     * @throws MethodNotAllowedException if no matching method is found for the route
     */
    public function __invoke()
    {
        $collector = new RouteCollector(new RouteParser(), new DataGenerator());

        $routes = $this->app['routes'];
        $dump = [];
        foreach ($routes as $route) {
            $dump = array_merge_recursive($dump, $route->dump());
        }

        foreach ($dump as $route => $methods) {
            foreach ($methods as $method => $handlers) {
                $collector->addRoute($method, $route, $handlers);
            }
        }

        $dispatcher = new Dispatcher($collector->getData());

        $request = $this->getRequest();
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException();
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new MethodNotAllowedHttpException($allowedMethods);
                break;
            case Dispatcher::FOUND:
                $this->app['context']->setParams($routeInfo[2]);
                foreach ($routeInfo[1] as $handler) {
                    $handler = new Handler($handler);
                    yield $handler;
                }
                break;
        }
    }
}
