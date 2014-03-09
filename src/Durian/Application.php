<?php

namespace Durian;

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
    const VERSION = '0.1.0';

    /**
     * Constructor.
     *
     * @param array $values Defaults to override
     */
    public function __construct(array $values = [])
    {
        parent::__construct();

        $this['debug'] = false;
        $this['app.catch_errors'] = true;

        $this['app.handler'] = $this->share(function ($app) {
            $handler = new Handler(null, null, ['iterate' => true]);
            $handler->handlers(array_map([$handler, 'handler'], $app['app.handlers']));

            return $handler;
        });

        $this['app.handlers'] = $this->share(function ($app) {
            return [
                new Middleware\ResponseMiddleware($app),
                new Middleware\RouterMiddleware($app)
            ];
        });

        $this['app.context'] = $this->share(function ($app) {
            return new ContextProxy();
        });

        $this['app.routes'] = $this->share(function ($app) {
            return new Route();
        });

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

        return $this['app.handler']->handler($handler, $test);
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
        if (null !== $handlers) {
            $handlers = array_map([$this, 'handler'], $handlers);
        }

        return $this['app.handler']->handlers($handlers, $replace);
    }

    /**
     * Prepend a handler to the middleware stack.
     *
     * @param mixed $handler A callable, value or service identifier
     * @param mixed $test    A callable or value
     */
    public function before($handler, $test = null)
    {
        $this['app.handler']->before($this->handler($handler), $test);
    }

    /**
     * Append a handler to the middleware stack.
     *
     * @param mixed $handler A callable, value or service identifier
     * @param mixed $test    A callable or value
     */
    public function after($handler, $test = null)
    {
        $this['app.handler']->after($this->handler($handler), $test);
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
        return call_user_func_array([$this['app.routes'], 'route'], func_get_args());
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

        $type = $this['app.context']->request()
            ? HttpKernelInterface::SUB_REQUEST
            : HttpKernelInterface::MASTER_REQUEST;

        $response = $this->handle($request, $type, $this['app.catch_errors']);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this['app.context']->request($request, $type);

        $this['app.handler']->options(['catch_errors' => $catch]);
        $this['app.handler']->context($this['app.context']);

        try {
            call_user_func($this['app.handler']);
        } finally {
            $response = $this['app.context']->response();
            $this['app.context']->clear();
        }

        return $response;
    }
}
