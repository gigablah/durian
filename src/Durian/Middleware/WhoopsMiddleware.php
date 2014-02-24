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
    private $whoops;

    /**
     * Register Whoops as an exception handler.
     */
    public function __construct()
    {
        $this->whoops = new Run();
        $this->whoops->pushHandler(new PrettyPageHandler());
        $this->whoops->register();
    }

    /**
     * Intercept exceptions and print the output.
     */
    public function __invoke()
    {
        try {
            yield;
        } catch (\Exception $exception) {
            $this->whoops->handleException($exception);
        }
    }
}
