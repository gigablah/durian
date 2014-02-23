<?php

namespace Durian;

use Durian\Middleware\AbstractMiddleware;

/**
 * Handlers consist of a payload and an optional test.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Handler
{
    private $payload;
    private $test;

    public function __construct($payload, $test = null)
    {
        $this->payload = $payload;
        $this->test = $test;
    }

    public function bindTo(Application $app)
    {
        if ($this->payload instanceof AbstractMiddleware) {
            $this->payload->bindTo($app);
        } elseif ($this->payload instanceof \Closure) {
            $this->payload = $this->payload->bindTo($app['context']);
        }

        if (null !== $this->test && $this->test instanceof \Closure) {
            $this->test = $this->test->bindTo($app['context']);
        }
    }

    public function __invoke()
    {
        if (is_callable($this->test) && !call_user_func($this->test)) {
            return null;
        }

        if (null !== $this->test && !(Boolean) $this->test) {
            return null;
        }

        return is_callable($this->payload) ? call_user_func($this->payload) : $this->payload;
    }
}
