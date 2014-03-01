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

    /**
     * Constructor.
     *
     * @param mixed $payload Callable to execute or value to return
     * @param mixed $test    Callable or value that determines if the handler is valid
     */
    public function __construct($payload = null, $test = null)
    {
        $this->payload = $payload;
        $this->test = $test;
    }

    /**
     * Bind to the application context.
     *
     * @param Application $app The application container
     */
    public function bindTo(Application $app)
    {
        if ($this->payload instanceof AbstractMiddleware) {
            $this->payload->bindTo($app);
        } elseif ($this->payload instanceof \Closure) {
            $this->payload = $this->payload->bindTo($app->context());
        }

        if (null !== $this->test && $this->test instanceof \Closure) {
            $this->test = $this->test->bindTo($app->context());
        }
    }

    /**
     * Evaluate the test (if any) and invoke the payload accordingly.
     *
     * @return mixed The handler output
     */
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
