<?php

namespace Durian;

use Durian\Middleware\AbstractMiddleware;

/**
 * Handlers consist of a callable and an optional test.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Handler
{
    private $callable;
    private $test;

    public function __construct(callable $callable, callable $test = null)
    {
        $this->callable = $callable;
        $this->test = $test;
    }

    public function bind(Application $app)
    {
        if ($this->callable instanceof AbstractMiddleware) {
            $this->callable->setApplication($app);
        } elseif ($this->callable instanceof \Closure) {
            $this->callable = $this->callable->bindTo($app['context']);
        }

        if (null !== $this->test && $this->test instanceof \Closure) {
            $this->test = $this->test->bindTo($app['context']);
        }
    }

    public function __invoke()
    {
        if (null === $this->test || call_user_func($this->test)) {
            return call_user_func($this->callable);
        }

        return null;
    }
}
