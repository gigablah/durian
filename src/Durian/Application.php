<?php

namespace Durian;

use Durian\Middleware\ResponseMiddleware;
use Durian\Middleware\RouterMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Durian Application container.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Application extends \Pimple implements HttpKernelInterface
{
    protected $handlers = [];
    protected $context = [];

    const VERSION = '0.0.1';

    /**
     * Constructor.
     *
     * @param array $values Defaults to override
     */
    public function __construct(array $values = [])
    {
        parent::__construct();

        $this['debug'] = false;
        $this['routes'] = [];

        $this->handlers = [
            new ResponseMiddleware(),
            new RouterMiddleware()
        ];

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    /**
     * Package a callable and optional test as a handler.
     *
     * @param mixed $handler A callable, value or service identifier
     * @param mixed $test    A callable or value
     *
     * @return Handler The packaged handler
     */
    public function handler($handler, $test = null)
    {
        if (!is_callable($handler) && isset($this[$handler])) {
            $handler = $this->raw($handler);
        }

        return $handler instanceof Handler ? $handler : new Handler($handler, $test);
    }

    /**
     * Prepend a handler to the middleware stack.
     *
     * @param mixed $handler A callable, value or service identifier
     * @param mixed $test    A callable or value
     */
    public function before($handler, $test = null)
    {
        array_unshift($this->handlers, $this->handler($handler, $test));
    }

    /**
     * Append a handler to the middleware stack.
     *
     * @param mixed $handler A callable, value or service identifier
     * @param mixed $test    A callable or value
     */
    public function after($handler, $test = null)
    {
        array_push($this->handlers, $this->handler($handler, $test));
    }

    /**
     * Manipulate the middleware stack.
     *
     * @param array   $handlers An array of handlers
     * @param Boolean $replace  Whether to replace the entire stack
     */
    public function handlers(array $handlers, $replace = true)
    {
        if ($replace) {
            $this->handlers = [];
        }

        foreach ($handlers as $handler) {
            $this->handlers[] = $this->handler($handler);
        }
    }

    /**
     * Associate a route segment with a collection of handlers.
     *
     * @param string $path     Path or pattern to match
     * @param mixed  $handlers Handlers to execute for the path segment
     *
     * @return Route A Route segment
     */
    public function route($path, $handlers = null)
    {
        if (null === $handlers) {
            $handlers = [function () {}];
        } else {
            $handlers = func_get_args();
            array_shift($handlers);
        }

        $route = Route::create($path, $handlers);
        $this['routes'] = array_merge($this['routes'], [$path => $route]);

        return $route;
    }

    /**
     * Insert request, retrieve response.
     *
     * @param string|Request $request The HTTP method or a Request object
     * @param string         $path    The requested path
     *
     * @return Response The HTTP Response
     */
    public function run($request = null, $path = null)
    {
        if (!$request instanceof Request) {
            if (null === $request) {
                $request = Request::createFromGlobals();
            } else {
                $request = Request::create($path, $request);
            }
        }

        $type = count($this->context)
            ? HttpKernelInterface::SUB_REQUEST
            : HttpKernelInterface::MASTER_REQUEST;

        $response = $this->handle($request, $type, !$this['debug']);

        if (HttpKernelInterface::SUB_REQUEST === $type) {
            return $response;
        }

        $response->send();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this['context'] = new Context($type, $request);
        array_push($this->context, $this['context']);

        $generators = [];

        // Produces a generator that recursively iterates through the handler stack
        $handlerCallback = function () {
            foreach ($this->handlers as $handler) {
                $result = (yield $handler);

                if ($result instanceof Handler) {
                    yield $result;
                } elseif ($result instanceof \Generator) {
                    $handler = $result->current();
                    while ($handler instanceof Handler) {
                        yield $handler;
                        $result->next();
                        $handler = $result->current();
                    }
                }
            }
        };

        $handlerGenerator = $handlerCallback();

        try {
            while ($handlerGenerator->valid()) {
                $handler = $handlerGenerator->current();
                $handler->bindTo($this);

                $result = call_user_func($handler);

                if ($result instanceof \Generator) {
                    $generator = $result;
                    $result = $generator->current();
                    array_push($generators, $generator);
                    $handlerGenerator->send($generator);
                } elseif ($result instanceof Handler) {
                    $handlerGenerator->send($result);
                } else {
                    $handlerGenerator->next();
                }

                if (!$result instanceof Handler) {
                    $this['context']->append($result);
                }
            }

            // Revisit all generators in reverse order
            while (count($generators)) {
                $generator = array_pop($generators);
                $generator->next();
            }
        } catch (\Exception $exception) {
            if (false === $catch) {
                throw $exception;
            }

            $caught = false;

            // Bubble the exception through all generators until handled
            while (count($generators)) {
                $generator = array_pop($generators);

                try {
                    if (!$caught) {
                        $generator->throw($exception);
                        $caught = true;
                    } else {
                        $generator->next();
                    }
                } catch (\Exception $exception) {
                    continue;
                }
            }
        }

        $response = $this['context']->response();

        array_pop($this->context);
        $this['context'] = end($this->context);

        return $response;
    }
}
