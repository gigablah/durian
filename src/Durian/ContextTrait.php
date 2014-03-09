<?php

namespace Durian;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Context proxy methods.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
trait ContextTrait
{
    /**
     * Set or get the request for the current context.
     *
     * @param Request $request The request object
     * @param integer $type    Request type (master or subrequest)
     *
     * @return Request The current request
     */
    public function request(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST)
    {
        return $this->context->request($request, $type);
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
        return $this->context->response($response, $status, $headers);
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
        $this->context->error($exception, $status, $headers, $code);
    } // @codeCoverageIgnore

    /**
     * Check whether the current request is a master or subrequest.
     *
     * @return Boolean True if master request, false otherwise
     */
    public function master()
    {
        return $this->context->master();
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
        return $this->context->params($param, $default);
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
        if (!func_num_args()) {
            return $this->context->last();
        }

        $this->context->last($output);
    }

    /**
     * Clear the current context.
     */
    public function clear()
    {
        $this->context->clear();
    }
}
