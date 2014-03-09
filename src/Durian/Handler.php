<?php

namespace Durian;

/**
 * Stores and recursively executes a stack of callbacks and handlers.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Handler
{
    protected $handlers;
    protected $test;
    protected $options;
    protected $context;

    /**
     * Constructor.
     *
     * @param mixed $handlers Callable, value or array of handlers
     * @param mixed $test     Callable or value that determines if the stack is valid
     * @param array $options  Array of configuration options
     */
    public function __construct($handlers = [], $test = null, array $options = [])
    {
        $this->handlers = is_array($handlers) ? $handlers : [$handlers];
        $this->test = $test;
        $this->options = array_replace([
            'iterate' => false,
            'catch_errors' => true,
            'terminate_on_response' => true
        ], $options);
    }

    /**
     * Set or retrieve the HTTP context.
     *
     * @param Context $context The HTTP context
     *
     * @return Context The HTTP context if no arguments are passed
     */
    public function context(Context $context = null)
    {
        if (null === $context) {
            return $this->context;
        }

        $this->context = $context;
    }

    /**
     * Set or retrieve options.
     *
     * @param mixed $options Option to retrieve or array of options to set
     *
     * @return mixed Option value(s)
     */
    public function options($options = null)
    {
        if (null === $options) {
            return $this->options;
        }

        if (is_array($options)) {
            $this->options = array_replace($this->options, $options);
        } elseif (array_key_exists($options, $this->options)) {
            return $this->options[$options];
        }

        return null;
    }

    /**
     * Package a callable and optional test as a handler.
     *
     * @param mixed $handler A callable or value
     * @param mixed $test    A callable or value
     *
     * @return mixed The packaged handler
     */
    public function handler($handler, $test = null)
    {
        if (null === $test && $handler instanceof Handler) {
            return $handler;
        }

        return new static($handler, $test);
    }

    /**
     * Retrieve or manipulate the handler stack.
     *
     * @param array   $handlers An array of handlers
     * @param Boolean $replace  Whether to replace the entire stack
     *
     * @return array The array of handlers if no arguments are passed
     */
    public function handlers(array $handlers = null, $replace = true)
    {
        if (null === $handlers) {
            return $this->handlers;
        }

        if ($replace) {
            $this->handlers = [];
        }

        $this->handlers = array_merge($this->handlers, $handlers);
    }

    /**
     * Prepend a handler to the stack.
     *
     * @param mixed $handler A callable or value
     * @param mixed $test    A callable or value
     */
    public function before($handler, $test = null)
    {
        array_unshift($this->handlers, $this->handler($handler, $test));
    }

    /**
     * Append a handler to the stack.
     *
     * @param mixed $handler A callable or value
     * @param mixed $test    A callable or value
     */
    public function after($handler, $test = null)
    {
        array_push($this->handlers, $this->handler($handler, $test));
    }

    /**
     * Evaluate the test (if any) and run the stack accordingly.
     *
     * @return mixed The stack output
     */
    public function __invoke()
    {
        try {
            if (null !== $this->test && !$this->call($this->test)) {
                return null;
            }
        } catch (Exception\TerminateException $exception) {
            return null;
        }

        return $this->run();
    }

    /**
     * Recursively iterate through the stack and execute each handler.
     *
     * @return mixed The last handler output
     */
    public function run()
    {
        if (!$this->handlers) {
            return null;
        }

        if (1 < count($this->handlers)) {
            $this->options['iterate'] = true;
        }

        $generators = [];
        $result = null;

        try {
            if (!$this->options['iterate']) {
                list($handler) = $this->handlers;

                return $this->call($handler);
            }

            foreach ($this->handlers as $result) {
                for (;;) {
                    if ($result instanceof \Generator) {
                        $generators[] = $result;
                        $result = $result->current();
                    } elseif ($result instanceof \Continuation) {
                        // @codeCoverageIgnoreStart
                        $generators[] = $result;
                        $result->next();
                        $result = $result->current();
                        // @codeCoverageIgnoreEnd
                    } elseif (is_callable($result)) {
                        $result = $this->call($result);
                    } else {
                        break;
                    }
                }

                if (null !== $this->context) {
                    $this->context->last($result);
                }
            }
        } catch (Exception\TerminateException $exception) {
        } catch (\Exception $exception) {
            if (false === $this->options['catch_errors']) {
                $generators = [];
                throw $exception;
            }

            $caught = false;

            // Propagate the exception through all generators until handled
            while (!$caught && $generator = array_pop($generators)) {
                try {
                    $generator->throw($exception);
                    $caught = true;
                } catch (\Exception $exception) {
                    continue;
                }
            }

            if (!$caught) {
                throw $exception;
            }
        } finally {
            // Revisit all generators in reverse order
            while ($generator = array_pop($generators)) {
                $generator->next();
            }
        }

        return $result;
    } // @codeCoverageIgnore

    /**
     * Resolve and/or execute a handler, callback or value.
     *
     * @param mixed $handler A handler, callback or value
     *
     * @return mixed The output
     */
    protected function call($handler)
    {
        $result = null;

        if (!is_callable($handler)) {
            $result = $handler;
        } elseif ($handler instanceof \Closure && defined('HHVM_VERSION')) {
            // temporary workaround for HHVM until Closure::bind is implemented
            $result = call_user_func($handler, $this->context); // @codeCoverageIgnore
        } else { // @codeCoverageIgnore
            if ($handler instanceof \Closure) {
                $handler = \Closure::bind($handler, $this->context);
            } elseif ($handler instanceof Handler) {
                $handler->context($this->context);
            }
            $result = call_user_func($handler);
        }

        if ($this->options['terminate_on_response']) {
            if (null !== $this->context && $this->context->response()) {
                throw new Exception\TerminateException();
            }
        }

        return $result;
    }
}
