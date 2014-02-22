<?php

namespace Durian;

/**
 * Routing context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Route
{
    private $path;
    private $handlers;
    private $parent;
    private $children;
    private $methods;

    public function __construct($path, array $handlers = array(), Route $parent = null)
    {
        $this->path = sprintf('%s/%s', $parent ? rtrim($parent->getPath(), '/') : '', $path);
        $this->handlers = $handlers;
        $this->parent = $parent;
        $this->children = array();
        $this->methods = array('GET' => array());
    }

    public static function create($path, array $handlers = array(), Route $parent = null)
    {
        return new static($path, $handlers, $parent);
    }

    public function dump()
    {
        $routes = array($this->path => array());

        foreach ($this->methods as $method => $methodHandlers) {
            $routes[$this->path][$method] = array_merge($this->handlers, $methodHandlers);
        }

        foreach ($this->children as $child) {
            foreach ($child->dump() as $path => $methods) {
                foreach ($methods as $method => $methodHandlers) {
                    $routes[$path][$method] = array_merge($this->handlers, $methodHandlers);
                }
            }
        }

        return $routes;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function route($path, callable $handlers = null)
    {
        if (null === $handlers) {
            $handlers = array(function () {});
        } else {
            $handlers = func_get_args();
            array_shift($handlers);
        }

        $this->children[] = $route = Route::create($path, $handlers, $this);

        return $route;
    }

    public function method($method, array $handlers = array())
    {
        $method = strtoupper($method);

        if (!isset($this->methods[$method])) {
            $this->methods[$method] = array();
        }

        $this->methods[$method] += $handlers;

        return $this;
    }

    public function get($handler)
    {
        return $this->method('GET', func_get_args());
    }

    public function post($handler)
    {
        return $this->method('POST', func_get_args());
    }

    public function put($handler)
    {
        return $this->method('PUT', func_get_args());
    }

    public function delete($handler)
    {
        return $this->method('DELETE', func_get_args());
    }

    public function patch($handler)
    {
        return $this->method('PATCH', func_get_args());
    }

    public function options($handler)
    {
        return $this->method('OPTIONS', func_get_args());
    }

    public function head($handler)
    {
        return $this->method('HEAD', func_get_args());
    }
}
