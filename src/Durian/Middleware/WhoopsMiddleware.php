<?php

namespace Durian\Middleware;

use Durian\Application;
use Durian\Middleware;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

/**
 * Provides pretty exception traces.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class WhoopsMiddleware extends Middleware
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

        parent::__construct($app);
    }

    /**
     * Intercept exceptions and print the output.
     */
    public function run()
    {
        try {
            yield null;
        } catch (\Exception $exception) {
            $this->app['whoops']->writeToOutput(false);
            $this->app['whoops']->allowQuit(false);
            if ($this->master()) {
                $this->response($this->app['whoops']->handleException($exception), 500);
            } else {
                throw $exception;
            }
        }
    }
}
