<?php

namespace Durian;

use Durian\Middleware\AbstractMiddleware;
use Durian\Middleware\RouterMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Durian Application container.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Application extends \Pimple implements HttpKernelInterface
{
    private $handlers = array();

    const VERSION = '0.0.1';

    public function __construct(array $values = array())
    {
        parent::__construct();

        $this['context'] = new Context();
        $this['routes'] = array();

        $this->handlers = array(new RouterMiddleware());

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
            $this->handlers = array();
        }

        foreach ($handlers as $handler) {
            $this->handlers[] = $this->handler($handler);
        }
    }

    public function route($path, callable $handlers = null)
    {
        if (null === $handlers) {
            $handlers = array(function () {});
        } else {
            $handlers = func_get_args();
            array_shift($handlers);
        }

        $route = Route::create($path, $handlers);
        $this['routes'] = array_merge($this['routes'], array($path => $route));

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

        $type = $this['context']->hasRequest()
            ? HttpKernelInterface::SUB_REQUEST
            : HttpKernelInterface::MASTER_REQUEST;

        $response = $this->handle($request, $type);
        $response->send();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this['context']->pushRequest($request);

        $generators = array();

        $handlerCallback = function () {
            foreach ($this->handlers as $handler) {
                $generator = (yield $handler);

                if ($generator instanceof \Generator) {
                    $handler = $generator->current();
                    while ($handler instanceof Handler) {
                        yield $handler;
                        $generator->next();
                        $handler = $generator->current();
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
                    array_push($generators, $generator);
                    $result = $generator->current();
                    $handlerGenerator->send($generator);
                } else {
                    $handlerGenerator->next();
                }
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
        $this['context']->popRequest();

        return $response;
    }
}
