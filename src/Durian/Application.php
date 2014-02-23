<?php

namespace Durian;

use Durian\Middleware\AbstractMiddleware;
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

    public function __construct(array $values = [])
    {
        parent::__construct();

        $this['routes'] = [];

        $this->handlers = [new RouterMiddleware()];

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function handler($handler, $test = null)
    {
        if (!is_callable($handler) && isset($this[$handler])) {
            $handler = $this->raw($handler);
        }

        return $handler instanceof Handler ? $handler : new Handler($handler, $test);
    }

    public function before($handler, $test = null)
    {
        array_unshift($this->handlers, $this->handler($handler, $test));
    }

    public function after($handler, $test = null)
    {
        array_push($this->handlers, $this->handler($handler, $test));
    }

    public function handlers(array $handlers, $replace = true)
    {
        if ($replace) {
            $this->handlers = [];
        }

        foreach ($handlers as $handler) {
            $this->handlers[] = $this->handler($handler);
        }
    }

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

        $response = $this->handle($request, $type);

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
        $this['context'] = new Context($request);
        array_push($this->context, $this['context']);

        $generators = [];

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

                if ($result instanceof Handler) {
                    $result = null;
                }

                $this['context']->append($result);
            }

            while (count($generators)) {
                $generator = array_pop($generators);
                $generator->next();
            }
        } catch (\Exception $exception) {
            if (false === $catch) {
                throw $exception;
            }

            $caught = false;
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

        $response = $this['context']->getResponse();

        array_pop($this->context);
        $this['context'] = end($this->context);

        return $response;
    }
}
