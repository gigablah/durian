<?php

namespace Durian\Middleware;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Prepares the response for output.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ResponseMiddleware extends AbstractMiddleware
{
    /**
     * Create a response if none is found, and prepare it.
     */
    public function __invoke()
    {
        try {
            yield;
        } catch (\Exception $exception) {
            if ($this->app['debug']) {
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
                $response = is_array($result) ? new JsonResponse($result) : new Response($result);
                $response->prepare($this->request());
                $this->response($response);
            }
        }
    }
}
