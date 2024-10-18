<?php
namespace Tanbolt\Container\Factory;

class SimpleKv implements SimpleKvInterface
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
}
