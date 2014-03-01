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
abstract class AbstractMiddleware
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
    protected function request(Request $request = null)
    {
        return $this->app->context()->request($request);
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
    protected function response($response = null, $status = 200, array $headers = [])
    {
        return $this->app->context()->response($response, $status, $headers);
    }

    /**
     * Throws an exception. Automatically maps to Symfony2's HttpException.
     *
     * @param string|\Exception $exception Exception message or object
     * @param integer           $status    HTTP status code
     * @param array             $headers   Response headers to set
     * @param mixed             $code      Exception code
     *
     * @throws \Exception
     */
    protected function error($exception = '', $status = 500, array $headers = [], $code = 0)
    {
        $this->app->context()->error($exception, $status, $headers, $code);
    } // @codeCoverageIgnore

    /**
     * Check whether the current request is a master or subrequest.
     *
     * @return Boolean True if master request, false otherwise
     */
    protected function master()
    {
        return $this->app->context()->master();
    }

    /**
     * Retrieve a route parameter.
     *
     * @param string $key     The parameter name
     * @param mixed  $default Fallback value
     *
     * @return mixed The route parameter
     */
    protected function param($key, $default = null)
    {
        return $this->app->context()->param($key, $default);
    }

    /**
     * Insert or retrieve route parameters.
     *
     * @param array $params Route parameters to insert
     *
     * @return array Route parameters if no arguments passed
     */
    protected function params(array $params = null)
    {
        return $this->app->context()->params($params);
    }

    /**
     * Insert handler output into the context.
     *
     * @param mixed $value Handler output
     */
    protected function append($output)
    {
        $this->app->context()->append($output);
    }

    /**
     * Retrieve the last handler output.
     *
     * @return mixed The last handler output
     */
    protected function last()
    {
        return $this->app->context()->last();
    }
}
