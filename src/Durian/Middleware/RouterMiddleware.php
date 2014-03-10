<?php

namespace Durian\Middleware;

use Durian\Application;
use Durian\Middleware;
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
class RouterMiddleware extends Middleware
{
    /**
     * Register the route collector and dispatcher.
     *
     * @param Application $app The application container
     */
    public function __construct(Application $app)
    {
        $app['router.collector'] = $app->share(function ($app) {
            return new RouteCollector(new RouteParser(), new DataGenerator());
        });

        $app['router.dispatcher'] = $app->share(function ($app) {
            foreach ($app['app.routes']->dump() as $route => $methods) {
                foreach ($methods as $method => $handlers) {
                    $app['router.collector']->addRoute($method, $route, $handlers);
                }
            }

            return new Dispatcher($app['router.collector']->getData());
        });

        parent::__construct($app);
    }

    /**
     * Compile all the routes and match the resulting regex against the current request.
     *
     * The handlers associated with all matched segments and methods are assembled and executed.
     *
     * @return mixed The last handler output
     *
     * @throws NotFoundHttpException if no matching route is found
     * @throws MethodNotAllowedException if no matching method is found for the route
     */
    public function run()
    {
        $request = $this->request();
        $result = $this->app['router.dispatcher']->dispatch($request->getMethod(), $request->getPathInfo());

        switch ($result[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException();
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $methods = $result[1];
                throw new MethodNotAllowedHttpException($methods);
                break;
            case Dispatcher::FOUND:
                $this->context->params($result[2]);
                $this->handlers = $result[1];
                break;
        }

        return parent::run();
    }
}
