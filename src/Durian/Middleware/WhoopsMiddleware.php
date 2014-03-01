<?php

namespace Durian\Middleware;

use Durian\Application;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

/**
 * Provides pretty exception traces.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class WhoopsMiddleware extends AbstractMiddleware
{
    /**
     * Register Whoops as an exception handler.
     *
     * @param Application $app The application container
     */
    public function __construct(Application $app)
    {
        $app['whoops'] = new Run();
        $app['whoops']->pushHandler(new PrettyPageHandler());
        $app['whoops']->register();
    }

    /**
     * Intercept exceptions and print the output.
     */
    public function __invoke()
    {
        try {
            yield;
        } catch (\Exception $exception) {
            $this->app['whoops']->writeToOutput(false);
            $this->app['whoops']->allowQuit(false);
            $this->response($this->app['whoops']->handleException($exception), 500);
        }
    }
}
