<?php

namespace Durian;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request/response context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Context
{
    private $params = [];
    private $output = [];
    private $type;
    private $request;
    private $response;

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
     * Set or get the request for the current context.
     *
     * @param Request $request The request object
     *
     * @return Request The current request
     */
    public function request(Request $request = null)
    {
        if (null === $request) {
            return $this->request;
        }

        $this->request = $request;
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
        if (null === $response) {
            return $this->response;
        }

        if (!$response instanceof Response) {
            $response = Response::create($response, $status, $headers);
        }

        $this->response = $response;
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
    public function error($exception, $status = 500, array $headers = [], $code = 0)
    {
        if ($exception instanceof \Exception) {
            $message = $exception->getMessage();
            $code = $code ?: $exception->getCode();
        } else {
            $message = $exception;
            $exception = null;
        }

        throw new HttpException($status, $message, $exception, $headers, $code);
    }

    /**
     * Check whether the current request is a master or subrequest.
     *
     * @return Boolean True if master request, false otherwise
     */
    public function master()
    {
        return HttpKernelInterface::MASTER_REQUEST === $this->type;
    }

    /**
     * Insert mapped route parameters into the context.
     *
     * @param array $params Mapped route parameters
     */
    public function map(array $params)
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
    public function append($output)
    {
        $this->output[] = $output;
    }

    /**
     * Retrieve the last handler output.
     *
     * @return mixed The last handler output
     */
    public function last()
    {
        return end($this->output);
    }
}
