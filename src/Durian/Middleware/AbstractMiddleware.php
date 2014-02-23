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
     * Check whether the context contains a request.
     *
     * @return Boolean True if the request is set, false otherwise
     */
    public function hasRequest()
    {
        return $this->app['context']->hasRequest();
    }

    /**
     * Retrieve the request from the context.
     *
     * @return Request The current request
     */
    public function getRequest()
    {
        return $this->app['context']->getRequest();
    }

    /**
     * Check whether the response has been set.
     *
     * @return Boolean True if the response is set, false otherwise
     */
    public function hasResponse()
    {
        return $this->app['context']->hasResponse();
    }

    /**
     * Retrieve the response from the context.
     *
     * @return Response The current response
     */
    public function getResponse()
    {
        return $this->app['context']->getResponse();
    }

    /**
     * Set the response for the current context.
     *
     * @param Response $response The response object
     */
    public function setResponse(Response $response)
    {
        $this->app['context']->setResponse($response);
    }
}
