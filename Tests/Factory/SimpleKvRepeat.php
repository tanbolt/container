<?php
namespace Tanbolt\Container\Factory;

class SimpleKvRepeat implements SimpleKvInterface
{
    protected $cache = [];

    public function __construct($key=null, $val=null)
    {
        if (!is_null($key)) {
            $this->set($key,$val);
        }
    }

    public function set($key, $val)
    {
        $this->cache[$key] = $val;
        return $this;
    }

    public function get($key)
    {
        return isset($this->cache[$key]) ? $this->cache[$key] : null;
    }


    public function __reset($key, $val)
    {
        $this->set($key,$val);
    }

}