<?php

namespace Durian;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Request/response context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Context
{
    protected $values = [];
    protected $params = [];
    protected $request;
    protected $response;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setResponse($response, $code = 200, array $headers = [])
    {
        if (!$response instanceof Response) {
            $response = Response::create($response, $code, $headers);
        }

        $this->response = $response;
    }

    public function getResponse()
    {
        if (!$this->hasResponse()) {
            $result = $this->last();
            $this->setResponse(is_array($result) ? new JsonResponse($result) : new Response($result));
        }

        return $this->response;
    }

    public function hasResponse()
    {
        return null !== $this->response;
    }

    public function setParams(array $params)
    {
        $this->params += $params;
    }

    public function param($key, $default = null)
    {
        return isset($this->params[$key]) ? $this->params[$key] : $default;
    }

    public function append($value)
    {
        array_push($this->values, $value);
    }

    public function last()
    {
        return end($this->values);
    }
}
