<?php

namespace Durian;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A HTTP context.
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
     * Set or get the request for the current context.
     *
     * @param Request $request The request object
     * @param integer $type    Request type (master or subrequest)
     *
     * @return Request The current request if no arguments passed
     */
    public function request(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST)
    {
        if (null === $request) {
            return $this->request;
        }

        $this->request = $request;
        $this->type = $type;
    }

    /**
     * Set or get the response for the current context.
     *
     * @param string|Response $response Response string or object
     * @param integer         $status   HTTP status code
     * @param array           $headers  Response headers to set
     *
     * @return Response The current response if no arguments passed
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
    public function error($exception = '', $status = 500, array $headers = [], $code = 0)
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
     * Insert or retrieve route parameters.
     *
     * @param string|array $param   Parameter name to return or array to insert
     * @param mixed        $default Fallback value
     *
     * @return array Route parameters if no arguments passed
     */
    public function params($param = null, $default = null)
    {
        if (null === $param) {
            return $this->params;
        }

        if (is_array($param)) {
            $this->params = $param + $this->params;
        } else {
            return array_key_exists($param, $this->params) ? $this->params[$param] : $default;
        }
    }

    /**
     * Insert or retrieve the last handler output.
     *
     * @param mixed $value Handler output
     *
     * @return mixed The last handler output if no arguments passed
     */
    public function last($output = null)
    {
        if (func_num_args()) {
            $this->output[] = $output;
        } elseif (count($this->output)) {
            return end($this->output);
        }

        return null;
    }

    /**
     * Clear the current context.
     */
    public function clear()
    {
        $this->params = [];
        $this->output = [];
        $this->type = null;
        $this->request = null;
        $this->response = null;
    }
}
