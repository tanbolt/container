<?php
namespace Tanbolt\Container\Factory;

interface SimpleKvInterface
{
    public function set($key,$val);

    public function get($key);
}