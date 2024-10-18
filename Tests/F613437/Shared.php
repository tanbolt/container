<?php
namespace Tanbolt\F613437;

class Shared
{
    public $foo = 0;

    public static function __shared()
    {
        return true;
    }

}
