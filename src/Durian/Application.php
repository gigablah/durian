<?php

namespace Durian;

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

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function handler($handler, $test = null)
    {
        $this->handlers[] = array($handler, $test);
    }

    public function route($path, callable $handlers = null)
    {
        if (null === $handlers) {
            $handlers = array(function () {
                yield;
            });
        } else {
            $handlers = func_get_args();
            array_shift($handlers);
        }

        $route = Route::create($path, $handlers);

        $this->handler(new Dispatcher($route, $this['context']));

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
        $generators = array();
        $this['context']->pushRequest($request);

        try {
            foreach ($this->handlers as list($handler, $test)) {
                if ($handler instanceof \Closure) {
                    $handler = $handler->bindTo($this['context']);
                }
                if ($test instanceof \Closure) {
                    $test = $test->bindTo($this['context']);
                }
                if (null === $test || call_user_func($test)) {
                    $result = call_user_func($handler);
                    if ($result instanceof \Generator) {
                        array_push($generators, $result);
                        $result = $result->current();
                    }
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

        $this['context']->popRequest();

        return $this['context']->getResponse();
    }
}
