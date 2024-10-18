<?php
namespace B923820\Foo;

class Foo implements FooInterface
{
    public static function __shared()
    {
        return true;
    }
}
