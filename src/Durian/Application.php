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
    protected $routes = [];
    protected $context = [];

    const VERSION = '0.0.3';

    /**
     * Constructor.
     *
     * @param array $values Defaults to override
     */
    public function __construct(array $values = [])
    {
        parent::__construct();

        $this['debug'] = false;
        $this['durian.send_response'] = true;
        $this['durian.bubble_errors'] = true;
        $this['durian.handler_class'] = 'Durian\\Handler';
        $this['durian.route_class'] = 'Durian\\Route';
        $this['durian.context_class'] = 'Durian\\Context';
        $this['durian.response_middleware'] = $this->share(function () {
            return new ResponseMiddleware();
        });
        $this['durian.router_middleware'] = $this->share(function () {
            return new RouterMiddleware();
        });

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        $this->handlers = [
            $this['durian.response_middleware'],
            $this['durian.router_middleware']
        ];
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

        return $handler instanceof $this['durian.handler_class']
            ? $handler
            : new $this['durian.handler_class']($handler, $test);
    }

    /**
     * Retrieve or manipulate the middleware stack.
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

        foreach ($handlers as $handler) {
            $this->handlers[] = $this->handler($handler);
        }
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

        $route = new $this['durian.route_class']($path, $handlers);

        $this->routes([$path => $route], false);

        return $route;
    }

    /**
     * Retrieve or manipulate the route collection.
     *
     * @param array   $routes  An array of routes
     * @param Boolean $replace Whether to replace the entire collection
     *
     * @return array The array of routes if no arguments are passed
     */
    public function routes(array $routes = null, $replace = true)
    {
        if (null === $routes) {
            return $this->routes;
        }

        if ($replace) {
            $this->routes = [];
        }

        $this->routes = array_merge($this->routes, $routes);
    }

    /**
     * Retrieve or append the current context.
     *
     * @param Context $context A new context
     *
     * @return Context The current context if no arguments are passed
     */
    public function context(Context $context = null)
    {
        if (null === $context) {
            return count($this->context) ? end($this->context) : null;
        }

        $this->context[] = $context;
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

        $response = $this->handle($request, $type, $this['durian.bubble_errors']);

        if (!$this['durian.send_response'] || HttpKernelInterface::SUB_REQUEST === $type) {
            return $response;
        }

        $response->send();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->context(new $this['durian.context_class']($request, $type));

        $handlerClass = $this['durian.handler_class'];
        $generators = [];

        // Produces a generator that recursively iterates through the handler stack
        $handlerCallback = function () use ($handlerClass) {
            foreach ($this->handlers as $handler) {
                $result = (yield $handler);

                if ($result instanceof $handlerClass) {
                    yield $result;
                } elseif ($result instanceof \Generator) {
                    $handler = $result->current();
                    while ($handler instanceof $handlerClass) {
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
                } elseif ($result instanceof $handlerClass) {
                    $handlerGenerator->send($result);
                } else {
                    $handlerGenerator->next();
                }

                if (!$result instanceof $handlerClass) {
                    $this->context()->append($result);
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

            if (!$caught) {
                throw $exception;
            }
        }

        $response = $this->context()->response();

        array_pop($this->context);

        return $response;
    }
}
