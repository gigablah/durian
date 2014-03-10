<?php

namespace Durian\Middleware;

use Durian\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Prepares the response for output.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ResponseMiddleware extends Middleware
{
    /**
     * Create a response if none is found, and prepare it.
     */
    public function run()
    {
        try {
            yield null;
        } catch (\Exception $exception) {
            if (!$this->master() || $this->app['debug']) {
                throw $exception;
            } elseif ($exception instanceof HttpException) {
                $this->response(
                    $exception->getMessage() ?: Response::$statusTexts[$exception->getStatusCode()],
                    $exception->getStatusCode(),
                    $exception->getHeaders()
                );
            } else {
                $this->response($exception->getMessage(), 500);
            }
        } finally {
            if (!$this->response()) {
                $result = $this->last();
                if (is_array($result)) {
                    $response = new JsonResponse($result);
                } elseif (is_int($result) && array_key_exists($result, Response::$statusTexts)) {
                    $response = new Response(null, $result);
                } else {
                    $response = new Response($result);
                }
                $response->prepare($this->request());
                $this->response($response);
            }
        }
    }
}
