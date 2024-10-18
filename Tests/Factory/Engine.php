<?php
namespace Tanbolt\Container\Factory;


class Engine
{
    public $engine;
    public $power;

    public function __construct($engine=null,$power=null)
    {
        $this->setEngine($engine,$power);
    }

    public function setEngine($engine=null,$power=null)
    {
        if (!is_null($engine)) {
            $this->engine = $engine;
        }
        if (!is_null($power)) {
            $this->power = $power;
        }
    }
}