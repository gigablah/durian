<?php

namespace Durian;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Request/response context.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
class Context implements \IteratorAggregate, \Countable
{
    private $bags = array();
    private $requests = array();
    private $response;

    public function pushRequest(Request $request)
    {
        array_push($this->bags, new ParameterBag());
        array_push($this->requests, $request);
    }

    public function popRequest()
    {
        if (!$this->hasRequest()) {
            return null;
        }

        array_pop($this->bags);

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
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function hasResponse()
    {
        return isset($this->response);
    }

    public function getBag()
    {
        if (!$this->hasRequest()) {
            return null;
        }

        return end($this->bags);
    }

    public function all()
    {
        return $this->getBag()->all();
    }

    public function keys()
    {
        return $this->getBag()->keys();
    }

    public function replace(array $parameters = array())
    {
        $this->getBag()->replace($parameters);
    }

    public function add(array $parameters = array())
    {
        $this->getBag()->add($parameters);
    }

    public function get($path, $default = null, $deep = false)
    {
        return $this->getBag()->get($path, $default, $deep);
    }

    public function set($key, $value)
    {
        $this->getBag()->set($key, $value);
    }

    public function has($key)
    {
        return $this->getBag()->has($key);
    }

    public function remove($key)
    {
        $this->getBag()->remove($key);
    }

    public function getAlpha($key, $default = '', $deep = false)
    {
        return $this->getBag()->getAlpha($key, $default, $deep);
    }

    public function getAlnum($key, $default = '', $deep = false)
    {
        return $this->getBag()->getAlnum($key, $default, $deep);
    }

    public function getDigits($key, $default = '', $deep = false)
    {
        return $this->getBag()->getDigits($key, $default, $deep);
    }

    public function getInt($key, $default = 0, $deep = false)
    {
        return $this->getBag()->getInt($key, $default, $deep);
    }

    public function filter($key, $default = null, $deep = false, $filter = FILTER_DEFAULT, $options = array())
    {
        return $this->getBag()->filter($key, $default, $deep, $filter, $options);
    }

    public function getIterator()
    {
        return $this->getBag()->getIterator();
    }

    public function count()
    {
        return $this->getBag()->count();
    }
}
