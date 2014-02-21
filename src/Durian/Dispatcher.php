<?php

namespace Durian;

/**
 * Route dispatcher.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Dispatcher
{
    private $route;
    private $context;

    public function __construct(Route $route, Context $context)
    {
        $this->route = $route;
        $this->context = $context;
    }

    public function __invoke()
    {
        $routes = $this->route->dump();

        $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $collector) use ($routes) {
            foreach ($routes as $route => $methods) {
                foreach ($methods as $method => $handlers) {
                    $collector->addRoute($method, $route, $handlers);
                }
            }
        });

        $request = $this->context->getRequest();

        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getRequestUri());

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException($allowedMethods);
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handlers = $routeInfo[1];
                $vars = $routeInfo[2];
                var_dump($handlers);exit;
                break;
        }
    }
}
