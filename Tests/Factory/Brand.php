<?php
namespace Tanbolt\Container\Factory;


class Brand
{
    public $brand = 'Benz';
    public $year = '1984';

    public function __construct($brand=null, $year=null)
    {
        $this->setBrand($brand,$year);
    }

    public function setBrand($brand=null, $year=null)
    {
        if (!is_null($brand)) {
            $this->brand = $brand;
        }
        if (!is_null($year)) {
            $this->year = $year;
        }
    }
}