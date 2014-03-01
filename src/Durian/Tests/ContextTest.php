<?php

namespace Durian\Tests;

use Durian\Context;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ContextTest.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ContextTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $context = new Context();

        $this->assertNull($context->request());
        $this->assertNull($context->response());
        $this->assertTrue($context->master());
        $this->assertNull($context->last());
    }

    public function testRequest()
    {
        $request1 = new Request();
        $context = new Context($request1);

        $this->assertSame($request1, $context->request());

        $request2 = new Request();
        $context->request($request2);

        $this->assertSame($request2, $context->request());
    }

    public function testResponse()
    {
        $response = new Response('foo');
        $context = new Context();
        $context->response($response);

        $this->assertSame($response, $context->response());
        $this->assertSame('foo', $context->response()->getContent());

        $context->response('I\'m a teapot', 418, ['X-Temperature-Celsius' => 50]);

        $this->assertSame('I\'m a teapot', $context->response()->getContent());
        $this->assertSame(418, $context->response()->getStatusCode());
        $this->assertSame(50, $context->response()->headers->get('X-Temperature-Celsius'));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function testError()
    {
        $context = new Context();

        $context->error();
    }

    public function testErrorWithException()
    {
        $context = new Context();

        try {
            $context->error(new \RuntimeException('foo'));
        } catch (\Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
            $this->assertSame('foo', $e->getMessage());
        }
    }

    public function testErrorWithFullArguments()
    {
        $context = new Context();

        try {
            $context->error('bar', 403, ['foo' => 'bar'], 42);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\HttpKernel\\Exception\\HttpException', $e);
            $this->assertSame('bar', $e->getMessage());
            $this->assertSame(['foo' => 'bar'], $e->getHeaders());
            $this->assertSame(42, $e->getCode());
        }
    }

    public function testMaster()
    {
        $context = new Context(null, HttpKernelInterface::MASTER_REQUEST);

        $this->assertTrue($context->master());

        $context = new Context(null, HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($context->master());
    }

    public function testParams()
    {
        $context = new Context();
        $context->params(['foo' => 'bar', 'bar' => 'foo']);

        $this->assertSame('bar', $context->param('foo'));
        $this->assertSame('foo', $context->param('bar'));

        $context->params(['foo' => 'baz']);

        $this->assertSame('baz', $context->param('foo'));
        $this->assertSame('foo', $context->param('bar'));

        $this->assertSame(['foo' => 'baz', 'bar' => 'foo'], $context->params());
    }

    public function testAppend()
    {
        $context = new Context();
        $context->append('foo');

        $this->assertSame('foo', $context->last());

        $context->append('bar');

        $this->assertSame('bar', $context->last());
    }
}
