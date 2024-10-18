<?php
namespace Tanbolt\Container\Factory;


class Car
{
    public $brand;
    public $engine;

    public function __construct(Brand $brand, Engine $engine)
    {
        $this->brand = $brand;
        $this->engine = $engine;
    }

    public function getCar()
    {
        return 'car';
    }

    public function getBrand()
    {
        return [$this->brand->brand, $this->brand->year];
    }

    public function getEngine()
    {
        return [$this->engine->engine, $this->engine->power];
    }

}