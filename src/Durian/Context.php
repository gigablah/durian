<?php

namespace Durian;

use Symfony\Component\HttpKernel\HttpKernelInterface;
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
    protected $type;
    protected $request;
    protected $response;

    /**
     * Constructor.
     *
     * @param string  $type    Request type
     * @param Request $request The Request object
     */
    public function __construct($type = HttpKernelInterface::MASTER_REQUEST, Request $request = null)
    {
        $this->type = $type;
        $this->request = $request;
    }

    /**
     * Set the request for the current context.
     *
     * @param Request $request The request object
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Retrieve the request from the context.
     *
     * @return Request The current request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Check whether the current request is a master or subrequest.
     *
     * @return Boolean True if master request, false otherwise
     */
    public function isMasterRequest()
    {
        return HttpKernelInterface::MASTER_REQUEST === $this->type;
    }

    /**
     * Set the response for the current context.
     *
     * @param string|Response $response Response string or a response object
     * @param integer         $code     HTTP status code
     * @param array           $headers  Response headers to set
     */
    public function setResponse($response, $code = 200, array $headers = [])
    {
        if (!$response instanceof Response) {
            $response = Response::create($response, $code, $headers);
        }

        $this->response = $response;
    }

    /**
     * Retrieve the response from the context.
     *
     * @return Response The current response
     */
    public function getResponse()
    {
        if (!$this->hasResponse()) {
            $result = $this->last();
            $this->setResponse(is_array($result) ? new JsonResponse($result) : new Response($result));
        }

        return $this->response;
    }

    /**
     * Check whether the response has been set.
     *
     * @return Boolean True if the response is set, false otherwise
     */
    public function hasResponse()
    {
        return null !== $this->response;
    }

    /**
     * Insert mapped route parameters into the context.
     *
     * @param array $params Mapped route parameters
     */
    public function setParams(array $params)
    {
        $this->params += $params;
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
        return isset($this->params[$key]) ? $this->params[$key] : $default;
    }

    /**
     * Insert handler output into the context.
     *
     * @param mixed $value Handler output
     */
    public function append($value)
    {
        array_push($this->values, $value);
    }

    /**
     * Retrieve the last handler output.
     *
     * @return mixed The last handler output
     */
    public function last()
    {
        return end($this->values);
    }
}
