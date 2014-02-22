<?php

namespace Durian;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request/response context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Context
{
    public $params = array();
    protected $requests = array();
    protected $responses = array();

    public function pushRequest(Request $request)
    {
        array_push($this->requests, $request);
        array_push($this->responses, null);
    }

    public function popRequest()
    {
        if (!$this->hasRequest()) {
            return null;
        }

        array_pop($this->responses);

        return array_pop($this->requests);
    }

    public function getRequest()
    {
        if (!$this->hasRequest()) {
            return null;
        }

        return end($this->requests);
    }

    public function hasRequest()
    {
        return count($this->requests) > 0;
    }

    public function setResponse(Response $response)
    {
        end($this->responses);
        $index = key($this->responses);
        $this->responses[$index] = $response;
    }

    public function getResponse()
    {
        return end($this->responses);
    }

    public function hasResponse()
    {
        return null !== end($this->responses);
    }
}
