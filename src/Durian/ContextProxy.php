<?php

namespace Durian;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Proxies the current HTTP context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class ContextProxy extends Context
{
    use ContextTrait;

    protected $contextClass;
    protected $contexts = [];
    protected $context;

    /**
     * Constructor.
     *
     * @param array  $contexts     Array of Context objects
     * @param string $contextClass Context class to instantiate
     */
    public function __construct(array $contexts = [], $contextClass = 'Durian\\Context')
    {
        $this->contextClass = $contextClass;
        $this->contexts = $contexts ?: [new $contextClass];
        $this->context = end($this->contexts);
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST)
    {
        if (null === $request) {
            return $this->context->request();
        }

        if ($this->context->request()) {
            $this->contexts[] = $this->context = new $this->contextClass;
        }

        $this->context->request($request, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if (1 < count($this->contexts)) {
            array_pop($this->contexts);
            $this->context = end($this->contexts);
        } else {
            $this->context->clear();
        }
    }
}
