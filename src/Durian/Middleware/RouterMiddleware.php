<?php

namespace Durian\Middleware;

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
     * @yields \Durian\Handler Handler associated with the matched route
     *
     * @throws NotFoundHttpException if no matching route is found
     * @throws MethodNotAllowedException if no matching method is found for the route
     */
    public function __invoke()
    {
        $collector = new RouteCollector(new RouteParser(), new DataGenerator());

        $dump = [];
        foreach ($this->app->routes() as $route) {
            $dump = array_merge_recursive($dump, $route->dump());
        }

        foreach ($dump as $route => $methods) {
            foreach ($methods as $method => $handlers) {
                $collector->addRoute($method, $route, $handlers);
            }
        }

        $dispatcher = new Dispatcher($collector->getData());

        $request = $this->request();
        $result = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());

        switch ($result[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException();
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $methods = $result[1];
                throw new MethodNotAllowedHttpException($methods);
                break;
            case Dispatcher::FOUND:
                $this->app->context()->params($result[2]);
                foreach ($result[1] as $handler) {
                    yield $this->app->handler($handler);
                }
                break;
        }
    }
}
