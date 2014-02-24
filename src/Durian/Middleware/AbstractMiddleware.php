<?php

namespace Durian\Middleware;

use Durian\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for middlewares.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class AbstractMiddleware
{
    protected $app;

    /**
     * Bind to the application context.
     *
     * @param Application $app The application container
     */
    public function bindTo(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Set or get the request for the current context.
     *
     * @param Request $request The request object
     *
     * @return Request The current request
     */
    public function request(Request $request = null)
    {
        return $this->app['context']->request($request);
    }

    /**
     * Set or get the response for the current context.
     *
     * @param string|Response $response Response string or object
     * @param integer         $status   HTTP status code
     * @param array           $headers  Response headers to set
     *
     * @return Response The current response
     */
    public function response($response = null, $status = 200, array $headers = [])
    {
        return $this->app['context']->response($response, $status, $headers);
    }

    /**
     * Retrieve a route parameter.
     *
     * @param string $key     The parameter name
     * @param mixed  $default Fallback value
     *
     * @return mixed The route parameter
     */
    public function param($key, $default = null)
    {
        return $this->app['context']->param($key, $default);
    }

    /**
     * Retrieve the last handler output.
     *
     * @return mixed The last handler output
     */
    public function last()
    {
        return $this->app['context']->last();
    }
}
