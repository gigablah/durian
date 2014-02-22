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

    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    public function hasRequest()
    {
        return $this->app['context']->hasRequest();
    }

    public function getRequest()
    {
        return $this->app['context']->getRequest();
    }

    public function hasResponse()
    {
        return $this->app['context']->hasResponse();
    }

    public function getResponse()
    {
        return $this->app['context']->getResponse();
    }

    public function setResponse(Response $response)
    {
        $this->app['context']->setResponse($response);
    }
}
