<?php

namespace Durian;

/**
 * Representation of a route segment.
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

    /**
     * Constructor.
     *
     * @param string $path     Path or pattern to match
     * @param array  $handlers Array of handlers to execute for the path segment
     * @param Route  $parent   Parent route to prepend
     */
    public function __construct($path, array $handlers = [], Route $parent = null)
    {
        $this->path = sprintf('%s%s', $parent ? rtrim($parent->getPath(), '/') : '', $path);
        $this->handlers = $handlers;
        $this->parent = $parent;
        $this->children = [];
        $this->methods = ['GET' => []];
    }

    /**
     * Create a new Route.
     *
     * @param string $path     Path or pattern to match
     * @param array  $handlers Array of handlers to execute for the path segment
     * @param Route  $parent   Parent route to prepend
     */
    public static function create($path, array $handlers = [], Route $parent = null)
    {
        return new static($path, $handlers, $parent);
    }

    /**
     * Recursively dump all routes to an array.
     *
     * @return array Routes in array form
     */
    public function dump()
    {
        $routes = [$this->path => []];

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

    /**
     * Retrieve the route path.
     *
     * @return string The route path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Create a new child route.
     *
     * @param string $path     Path or pattern to match
     * @param array  $handlers Array of handlers to execute for the path segment
     *
     * @return Route The child route
     */
    public function route($path, callable $handlers = null)
    {
        if (null === $handlers) {
            $handlers = [function () {}];
        } else {
            $handlers = func_get_args();
            array_shift($handlers);
        }

        $this->children[] = $route = Route::create($path, $handlers, $this);

        return $route;
    }

    /**
     * Handle a HTTP method for the route.
     *
     * @param string $method   HTTP method to match
     * @param array  $handlers Array of handlers to execute for the method
     *
     * @return Route The current route
     */
    public function method($method, array $handlers = [])
    {
        $method = strtoupper($method);

        if (!isset($this->methods[$method])) {
            $this->methods[$method] = [];
        }

        $this->methods[$method] += $handlers;

        return $this;
    }

    /**
     * Handle the GET method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function get($handler)
    {
        return $this->method('GET', func_get_args());
    }

    /**
     * Handle the POST method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function post($handler)
    {
        return $this->method('POST', func_get_args());
    }

    /**
     * Handle the PUT method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function put($handler)
    {
        return $this->method('PUT', func_get_args());
    }

    /**
     * Handle the DELETE method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function delete($handler)
    {
        return $this->method('DELETE', func_get_args());
    }

    /**
     * Handle the PATCH method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function patch($handler)
    {
        return $this->method('PATCH', func_get_args());
    }

    /**
     * Handle the OPTIONS method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function options($handler)
    {
        return $this->method('OPTIONS', func_get_args());
    }

    /**
     * Handle the HEAD method for the route.
     *
     * @param mixed $handler Handlers to execute
     *
     * @return Route The current route
     */
    public function head($handler)
    {
        return $this->method('HEAD', func_get_args());
    }
}
