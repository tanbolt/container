<?php
use PHPUnit\Framework\TestCase;
use Tanbolt\Container\Container;
use Tanbolt\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    public function setUp():void
    {
        PHPUNIT_LOADER::addDir('Tanbolt\Container\Factory', __DIR__.'/Factory');
        parent::setUp();
    }

    public function testInstance()
    {
        $ioc = new Container();
        static::assertSame($ioc, $ioc::instance());
        static::assertSame($ioc, Container::instance());
        static::assertSame($ioc, $ioc->load(ContainerInterface::class));
    }

    public function testBindMethod()
    {
        $alias = 'SimpleKv';
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';

        // bind concrete
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bind($concrete));
        static::assertTrue($ioc->isBound($concrete));
        static::assertFalse($ioc->isShared($concrete));

        static::assertInstanceOf($abstract, $ioc->load($concrete));
        static::assertInstanceOf($concrete, $ioc->load($concrete));
        static::assertNotSame($ioc->load($concrete), $ioc->load($concrete)); // not shared

        // bind [alias => concrete]
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bind([$alias => $concrete]));
        static::assertTrue($ioc->isAlias($alias));
        static::assertTrue($ioc->isBound($alias));
        static::assertTrue($ioc->isBound($concrete));
        static::assertFalse($ioc->isShared($concrete));

        static::assertInstanceOf($concrete, $ioc->load($alias));
        static::assertInstanceOf($concrete, $ioc->load($concrete));

        // bind [alias => abstract] => concrete
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bind([$alias => $abstract], $concrete));
        static::assertTrue($ioc->isAlias($alias));
        static::assertTrue($ioc->isBound($alias));
        static::assertTrue($ioc->isBound($abstract));
        static::assertFalse($ioc->isBound($concrete));

        static::assertInstanceOf($concrete, $ioc->load($alias));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        // overlay bind
        $ioc->bind($abstract, $repeat);
        static::assertTrue($ioc->isAlias($alias));
        static::assertTrue($ioc->isBound($alias));
        static::assertTrue($ioc->isBound($abstract));

        static::assertInstanceOf($repeat, $ioc->load($alias));
        static::assertInstanceOf($repeat, $ioc->load($abstract));

        $keys = array_keys($ioc->binds());
        static::assertEquals([$abstract], $keys);
    }

    public function testBindIfMethod()
    {
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';

        // bindIf different abstract
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bindIf($concrete));
        static::assertTrue($ioc->isBound($concrete));
        static::assertInstanceOf($concrete, $ioc->load($concrete));

        static::assertSame($ioc, $ioc->bindIf($repeat));
        static::assertTrue($ioc->isBound($repeat));
        static::assertInstanceOf($repeat, $ioc->load($repeat));

        // bindIf duplicate
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bindIf($abstract, $concrete));
        static::assertTrue($ioc->isBound($abstract));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        static::assertSame($ioc, $ioc->bindIf($abstract, $repeat));
        static::assertTrue($ioc->isBound($abstract));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        // bind abstract alias duplicate
        $ioc = new Container();
        $ioc->bindIf([$abstract => 'alias'], $concrete);
        static::assertTrue($ioc->isBound('alias'));
        static::assertTrue($ioc->isBound($abstract));
        static::assertInstanceOf($concrete, $ioc->load('alias'));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        $ioc->bindIf('alias', $repeat);
        static::assertTrue($ioc->isBound('alias'));
        static::assertTrue($ioc->isBound($abstract));
        static::assertInstanceOf($concrete, $ioc->load('alias'));

        // bindIf duplicate with different alias
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bindIf(['a' => $abstract], $concrete));
        static::assertTrue($ioc->isBound('a'));
        static::assertTrue($ioc->isBound($abstract));
        static::assertInstanceOf($concrete, $ioc->load('a'));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        static::assertSame($ioc, $ioc->bindIf(['b' => $abstract], $concrete));
        static::assertTrue($ioc->isBound('b'));
        static::assertInstanceOf($concrete, $ioc->load('b'));
        static::assertInstanceOf($concrete, $ioc->load($abstract));

        static::assertSame($ioc, $ioc->bindIf(['c' => $abstract], $repeat));
        static::assertTrue($ioc->isBound('c'));
        static::assertInstanceOf($concrete, $ioc->load('c'));
        static::assertInstanceOf($concrete, $ioc->load($abstract));
    }

    public function testBindSharedMethod()
    {
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';

        // bindShared concrete
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bindShared($concrete));
        static::assertTrue($ioc->isBound($concrete));
        static::assertTrue($ioc->isShared($concrete));
        static::assertInstanceOf($concrete, $ioc->load($concrete));
        static::assertSame($ioc->load($concrete), $ioc->load($concrete));

        // bindShared abstract=>concrete
        $ioc = new Container();
        static::assertSame($ioc, $ioc->bindShared($abstract, $concrete));
        static::assertTrue($ioc->isBound($abstract));
        static::assertTrue($ioc->isShared($abstract));
        static::assertFalse($ioc->isBound($concrete));
        static::assertFalse($ioc->isShared($concrete));

        // not have __reset method
        $object = $ioc->load($abstract, 'foo', 'bar');
        static::assertInstanceOf($concrete, $object);
        static::assertEquals('bar', $object->get('foo'));

        $object2 = $ioc->load($abstract, 'foo', 'bar2');
        static::assertSame($object, $object2);
        static::assertEquals('bar', $object->get('foo'));

        // bindShared overlay and have __reset method
        $ioc->bindShared($abstract, $repeat);
        $overlay = $ioc->load($abstract, 'foo', 'bar3');
        static::assertNotSame($object, $overlay);
        static::assertInstanceOf($repeat, $overlay);
        static::assertEquals('bar3', $overlay->get('foo'));

        $overlay2 = $ioc->load($abstract, 'foo', 'bar4');
        static::assertSame($overlay, $overlay2);
        static::assertEquals('bar4', $overlay2->get('foo'));

        $overlay2 = $ioc->load($abstract);
        static::assertSame($overlay, $overlay2);
        static::assertEquals('bar4', $overlay2->get('foo'));
    }

    public function testIsBoundMethod()
    {
        $ioc = new Container();
        $ioc->bind('\\Name\\ClassName');
        $ioc->bind('Name\\Interface', 'Name\\Class');
        $ioc->bind(['alias' => '\\Name\\Contract'], '\\Name\\Concrete');
        $ioc->extend('Foo', 'foo');
        $ioc->extend('\\Bar', 'bar');

        static::assertTrue($ioc->isBound('\\Name\\ClassName'));
        static::assertTrue($ioc->isBound('Name\\ClassName'));

        static::assertTrue($ioc->isBound('\\Name\\Interface'));
        static::assertTrue($ioc->isBound('Name\\Interface'));
        static::assertFalse($ioc->isBound('Name\\Class'));
        static::assertFalse($ioc->isBound('\\Name\\Class'));

        static::assertTrue($ioc->isBound('alias'));
        static::assertTrue($ioc->isBound('\\alias'));
        static::assertTrue($ioc->isBound('\\Name\\Contract'));
        static::assertTrue($ioc->isBound('Name\\Contract'));
        static::assertFalse($ioc->isBound('\\Name\\Concrete'));
        static::assertFalse($ioc->isBound('Name\\Concrete'));

        static::assertTrue($ioc->isBound('Foo'));
        static::assertTrue($ioc->isBound('\\Foo'));
        static::assertTrue($ioc->isBound('Bar'));
        static::assertTrue($ioc->isBound('\\Bar'));

        static::assertFalse($ioc->isBound('none'));
    }

    public function testIsSharedMethod()
    {
        $ioc = new Container();
        $ioc->bind('\\Name\\ClassName');
        $ioc->bindShared('Name\\Interface', 'Name\\Class');
        $ioc->bindShared(['alias' => '\\Name\\Contract'], '\\Name\\Concrete');
        $ioc->extend('Foo', 'foo');
        $ioc->extend('\\Bar', 'bar');

        static::assertFalse($ioc->isShared('\\Name\\ClassName'));
        static::assertFalse($ioc->isShared('Name\\ClassName'));

        static::assertTrue($ioc->isShared('Name\\Interface'));
        static::assertTrue($ioc->isShared('\\Name\\Interface'));
        static::assertFalse($ioc->isShared('Name\\Class'));
        static::assertFalse($ioc->isShared('\\Name\\Class'));

        static::assertTrue($ioc->isShared('alias'));
        static::assertTrue($ioc->isShared('\\alias'));
        static::assertTrue($ioc->isShared('\\Name\\Contract'));
        static::assertTrue($ioc->isShared('Name\\Contract'));
        static::assertFalse($ioc->isShared('\\Name\\Concrete'));
        static::assertFalse($ioc->isShared('Name\\Concrete'));

        static::assertTrue($ioc->isShared('Foo'));
        static::assertTrue($ioc->isShared('\\Foo'));
        static::assertTrue($ioc->isShared('Bar'));
        static::assertTrue($ioc->isShared('\\Bar'));

        static::assertFalse($ioc->isShared('none'));
    }

    public function testExtendMethod()
    {
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';

        $ioc = new Container();
        $ioc->bind('foo', $concrete);

        $object = $ioc->load('foo');
        static::assertInstanceOf($concrete, $object);

        static::assertSame($ioc, $ioc->extend('foo', 'foo'));
        $ioc->extend('bar', 'bar');
        static::assertEquals('foo',  $ioc->load('foo'));
        static::assertEquals('bar',  $ioc->load('bar'));
    }

    public function testAliasMethod()
    {
        $ioc = new Container();
        static::assertSame($ioc, $ioc->alias('bar', 'foo'));
        $ioc->alias('\\b', 'a')->alias('\\c', '\\b')->alias('\\x', '\\d');

        static::assertEquals('bar', $ioc->aliasName('foo'));
        static::assertEquals('c', $ioc->aliasName('a'));
        static::assertEquals('c', $ioc->aliasName('\\b'));
        static::assertEquals('x', $ioc->aliasName('\\d'));

        static::assertTrue($ioc->isAlias('foo'));
        static::assertTrue($ioc->isAlias('\\b'));

        static::assertFalse($ioc->isAlias('nothing'));
        static::assertEquals('nothing', $ioc->aliasName('nothing'));
    }

    protected function checkLoadOrCallConcrete(Tanbolt\Container\Factory\Car $car, $checkPropos = false)
    {
        static::assertInstanceOf('Tanbolt\Container\Factory\Car', $car);
        static::assertInstanceOf('Tanbolt\Container\Factory\Brand', $car->brand);
        static::assertInstanceOf('Tanbolt\Container\Factory\Engine', $car->engine);
        if ($checkPropos) {
            static::assertEquals(['Benz','1984'], $car->getBrand());
            $car->brand->setBrand('bmw','1986');
            static::assertEquals(['bmw','1986'], $car->getBrand());
        }
    }

    public function testLoadOrCallConcrete()
    {
        // load bind
        $ioc = new Container();
        $ioc->bind(['car' => 'Tanbolt\Container\Factory\Car']);

        $this->checkLoadOrCallConcrete($ioc->load('car'), true);
        $this->checkLoadOrCallConcrete($car = $ioc->load('car'));
        static::assertEquals(['Benz','1984'], $car->getBrand());

        $this->checkLoadOrCallConcrete($ioc->load('Tanbolt\Container\Factory\Car'), true);
        $this->checkLoadOrCallConcrete($car = $ioc->load('Tanbolt\Container\Factory\Car'));
        static::assertEquals(['Benz','1984'], $car->getBrand());

        // load shared bind
        $ioc = new Container();
        $ioc->bindShared(['car' => 'Tanbolt\Container\Factory\Car']);
        static::assertTrue($ioc->isShared('car'));

        $this->checkLoadOrCallConcrete($car = $ioc->load('car'), true);
        static::assertSame($car, $car2 = $ioc->load('car'));
        static::assertEquals(['bmw','1986'], $car2->getBrand());
        static::assertSame($car, $car2 = $ioc->load('Tanbolt\Container\Factory\Car'));
        static::assertEquals(['bmw','1986'], $car2->getBrand());

        // load not bind
        $ioc = new Container();
        static::assertFalse($ioc->isBound('SimpleClass'));
        static::assertInstanceOf('SimpleClass', $simple = $ioc->load('SimpleClass'));
        static::assertEquals('foo', $simple->name);
        static::assertEquals([], $simple->args);
        static::assertInstanceOf('SimpleClass', $simple = $ioc->load('SimpleClass', 'bar', 'biz'));
        static::assertEquals('bar', $simple->name);
        static::assertEquals(['bar', 'biz'], $simple->args);

        // call
        $ioc = new Container();
        try {
            $ioc->call('car');
            static::fail('It should be throw exception');
        } catch (InvalidArgumentException $e) {
            static::assertTrue(true);
        }
        $this->checkLoadOrCallConcrete($ioc->call('Tanbolt\Container\Factory\Car'), true);
        $this->checkLoadOrCallConcrete($car = $ioc->call('Tanbolt\Container\Factory\Car'));
        static::assertEquals(['Benz','1984'], $car->getBrand());

        static::assertInstanceOf('SimpleClass', $simple = $ioc->call('SimpleClass'));
        static::assertEquals('foo', $simple->name);
        static::assertEquals([], $simple->args);
        static::assertInstanceOf('SimpleClass', $simple = $ioc->call('SimpleClass', 'bar', 'biz'));
        static::assertEquals('bar', $simple->name);
        static::assertEquals(['bar', 'biz'], $simple->args);
    }

    public function iocFunction($name = 'ioc')
    {
        return $name.':'.join('_', func_get_args());
    }

    public function IocFunctionResolve($name = 'iocResolve', FooClass $foo = null)
    {
        $rs = $name;
        if ($foo) {
            $rs .= '_'. $foo->foo;
        }
        if (count($args = func_get_args()) > 2) {
            $rs .= join('_', array_slice($args, 2));
        }
        return $rs;
    }

    protected function verifyLoadOrCallCallable($ioc, $method, $IocFunction, $IocFunctionResolve)
    {
        // call
        static::assertEquals('ioc:', $ioc->$method($IocFunction));
        static::assertEquals('foo:foo_bar', $ioc->$method($IocFunction, 'foo', 'bar'));
        static::assertEquals('foo:foo_bar_biz', $ioc->$method($IocFunction, 'foo', 'bar','biz'));

        static::assertEquals('iocResolve_foo', $ioc->$method($IocFunctionResolve));
        static::assertEquals('foo_foo', $ioc->$method($IocFunctionResolve, 'foo'));
        static::assertEquals('foo', $ioc->$method($IocFunctionResolve, 'foo', null));
        static::assertEquals('foobar_biz', $ioc->$method($IocFunctionResolve, 'foo', null, 'bar', 'biz'));

        $foo = new FooClass();
        $foo->foo = 'fooClass';
        static::assertEquals('foo_fooClass', $ioc->$method($IocFunctionResolve, 'foo', $foo));
        static::assertEquals('foo_fooClassbar_biz', $ioc->$method($IocFunctionResolve, 'foo', $foo, 'bar', 'biz'));
    }

    protected function checkLoadOrCallCallable($ioc, $IocFunction, $IocFunctionResolve, $function = null, $functionResolve = null)
    {
        $this->verifyLoadOrCallCallable($ioc, 'load', $IocFunction, $IocFunctionResolve);
        $this->verifyLoadOrCallCallable($ioc, 'call', $IocFunction, $IocFunctionResolve);

        if ($function && $functionResolve) {
            $this->verifyLoadOrCallCallable($ioc, 'load', $function,  $functionResolve);
            try {
                $this->verifyLoadOrCallCallable($ioc, 'call', $function,  $functionResolve);
                static::fail('It should be throw exception');
            } catch (InvalidArgumentException $e) {
                static::assertTrue(true);
            }
        }
    }

    public function testLoadOrCallCallable()
    {
        // Closure
        $ioc = new Container();
        $IocFunction = function($name = 'ioc') {
            return $name.':'.join('_', func_get_args());
        };
        $IocFunctionResolve = function ($name = 'iocResolve', FooClass $foo = null) {
            $rs = $name;
            if ($foo) {
                $rs .= '_'. $foo->foo;
            }
            if (count($args = func_get_args()) > 2) {
                $rs .= join('_', array_slice($args, 2));
            }
            return $rs;
        };
        $ioc->bind('f', $IocFunction);
        $ioc->bind('fr', $IocFunctionResolve);
        $this->checkLoadOrCallCallable($ioc, $IocFunction, $IocFunctionResolve, 'f', 'fr');


        $IocFunction = 'IocFunction';
        $IocFunctionResolve = 'IocFunctionResolve';

        // function name
        $ioc = new Container();
        $ioc->bind('f', $IocFunction);
        $ioc->bind('fr', $IocFunctionResolve);
        $this->checkLoadOrCallCallable($ioc, $IocFunction, $IocFunctionResolve, 'f', 'fr');

        // className@method
        $ioc = new Container();
        $ioc->bind('f', 'BarClass');
        $this->checkLoadOrCallCallable(
            $ioc,
            'BarClass@'.$IocFunction,
            'BarClass@'.$IocFunctionResolve,
            'f@'.$IocFunction,
            'f@'.$IocFunctionResolve
        );

        // [object, method]
        $ioc = new Container();
        $this->checkLoadOrCallCallable(
            $ioc,
            [$this, $IocFunction],
            [$this, $IocFunctionResolve]
        );

        // [class, staticMethod]
        $ioc = new Container();
        $this->checkLoadOrCallCallable(
            $ioc,
            ['BizClass', $IocFunction],
            ['BizClass', $IocFunctionResolve]
        );

        // className::method
        $ioc = new Container();
        $this->checkLoadOrCallCallable(
            $ioc,
            'BizClass'.'::'.$IocFunction,
            'BizClass'.'::'.$IocFunctionResolve
        );
    }

    public function testOnceMethod()
    {
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';

        $ioc = new Container();
        $ioc->bind($abstract, $concrete);
        $kv = $ioc->load($abstract);
        $kv2 = $ioc->once($abstract);
        static::assertNotSame($kv, $ioc->load($abstract));
        static::assertNotSame($kv2, $ioc->load($abstract));
        static::assertNotSame($kv, $ioc->once($abstract));
        static::assertNotSame($kv2, $ioc->once($abstract));

        $ioc = new Container();
        $ioc->bindShared($abstract, $concrete);
        $kv = $ioc->load($abstract);
        $kv2 = $ioc->once($abstract);
        static::assertSame($kv, $ioc->load($abstract));
        static::assertNotSame($kv2, $ioc->load($abstract));
        static::assertNotSame($kv, $ioc->once($abstract));
        static::assertNotSame($kv2, $ioc->once($abstract));
    }

    public function testOnMakeCallback()
    {
        $ioc = new Container();
        $ioc->bind(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bind(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\CountInterface', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bind(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bind('Tanbolt\Container\Factory\CountInterface', 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\CountInterface', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bind('Tanbolt\Container\Factory\CountInterface', 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bind('Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);

        $ioc = new Container();
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
    }

    public function testOnMakeSharedCallback()
    {
        $ioc = new Container();
        $ioc->bindShared(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bindShared(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\CountInterface', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bindShared(['count' => 'Tanbolt\Container\Factory\CountInterface'], 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bindShared('Tanbolt\Container\Factory\CountInterface', 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\CountInterface', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(0, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bindShared('Tanbolt\Container\Factory\CountInterface', 'Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\CountInterface')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);


        $ioc = new Container();
        $ioc->bindShared('Tanbolt\Container\Factory\Count');
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);

        $ioc = new Container();
        $ioc->onMake('Tanbolt\Container\Factory\Count', function($obj) {
            $obj->foo++;
        });
        static::assertEquals(1, $ioc->load('Tanbolt\Container\Factory\Count')->foo);
    }

    public function makeCallback($object)
    {
        $object->set('b', 'b');
    }

    public function globalMakeCallback($object)
    {
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';
        if ($object instanceof $concrete) {
            $object->set('sign', 'kv');
        } elseif ($object instanceof $repeat) {
            $object->set('sign', 'kvr');
        }
    }

    public function testOnMakeCallbackGlobal()
    {
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';

        $alias = 'kv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';

        $ioc = new Container();
        $ioc->bind($abstract, $concrete);
        $ioc->bindShared($alias, $repeat);

        // global callback
        $ifCall = false;
        $ioc->onMake([$this, 'globalMakeCallback']);
        $ioc->onMake(function($object) use ($concrete, $repeat, &$ifCall) {
            $sign = $object->get('sign');
            static::assertNotEmpty($sign);
            if ('kv' == $sign) {
                static::assertInstanceOf($concrete, $object);
            } elseif ('kvr' == $sign) {
                static::assertInstanceOf($repeat, $object);
            }
            $ifCall = true;
        });

        // bind callback
        $ioc->onMake($abstract, function($kv){
            $kv->set('a', 'a');
        });
        $ioc->onMake($abstract, [$this, 'makeCallback']);
        $ioc->onMake($abstract, 'makeCallbackFunction');

        $object = $ioc->load($abstract ,'foo', 'bar');
        static::assertEquals('bar', $object->get('foo'));
        static::assertEquals('kv', $object->get('sign'));
        static::assertEquals('a', $object->get('a'));
        static::assertEquals('b', $object->get('b'));
        static::assertEquals('c', $object->get('c'));
        static::assertTrue($ifCall);

        // not shared, load again
        $ifCall = false;
        $object->set('sign', 'kv2')->set('a', 'aa')->set('b', 'bb')->set('c', 'cc');
        $object = $ioc->load($abstract ,'foo', 'bar2');
        static::assertEquals('bar2', $object->get('foo'));
        static::assertEquals('kv', $object->get('sign'));
        static::assertEquals('a', $object->get('a'));
        static::assertEquals('b', $object->get('b'));
        static::assertEquals('c', $object->get('c'));
        static::assertTrue($ifCall);

        // bindShared callback
        $ioc->onMake($alias, function($kv){
            $kv->set('d', 'd');
        });
        $object = $ioc->load($alias ,'foo', 'bar');
        static::assertEquals('bar', $object->get('foo'));
        static::assertEquals('kvr', $object->get('sign'));
        static::assertEquals('d', $object->get('d'));

        // shared, load again
        $ifCall = false;
        $object->set('sign', 'kvr2')->set('d', 'dd');
        $object = $ioc->load($alias ,'foo', 'bar2');
        static::assertEquals('bar2', $object->get('foo'));
        static::assertEquals('kvr2', $object->get('sign'));
        static::assertEquals('dd', $object->get('d'));
        static::assertFalse($ifCall);
    }

    public function testIsMakeMethod()
    {
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';
        $repeat =  'Tanbolt\\Container\\Factory\\SimpleKvRepeat';

        $ioc = new Container();
        $ioc->bind($concrete);
        $ioc->bindShared($repeat);

        static::assertFalse($ioc->isMake($concrete));
        static::assertFalse($ioc->isMake($repeat));

        $ioc->load($concrete);
        $ioc->load($repeat);

        static::assertFalse($ioc->isMake($concrete));
        static::assertTrue($ioc->isMake($repeat));
    }

    public function testClearMethod()
    {
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';

        $ioc = new Container();
        $ioc->bind('foo', $concrete);
        $a = $ioc->load('foo');
        static::assertTrue($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $b = $ioc->load('foo'));
        static::assertNotSame($a, $b);

        static::assertSame($ioc, $ioc->clear('foo'));
        static::assertTrue($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $c = $ioc->load('foo'));
        static::assertNotSame($b, $c);


        $ioc = new Container();
        $ioc->bindShared('foo', $concrete);
        $a = $ioc->load('foo');
        static::assertTrue($ioc->isBound('foo'));
        static::assertTrue($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $b = $ioc->load('foo'));
        static::assertSame($a, $b);

        static::assertSame($ioc, $ioc->clear('foo'));
        static::assertTrue($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $c = $ioc->load('foo'));
        static::assertNotSame($b, $c);

        $ioc = new Container();
        $ioc->extend('foo', new $concrete);
        $a = $ioc->load('foo');
        static::assertTrue($ioc->isBound('foo'));
        static::assertTrue($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $b = $ioc->load('foo'));
        static::assertSame($a, $b);
        static::assertSame($ioc, $ioc->clear('foo'));
        static::assertFalse($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        try {
            $ioc->load('foo');
            static::fail('It should be throw exception when target is not instantiable');
        } catch (Exception $e) {
            // do nothing
        }
    }

    public function testFlushMethod()
    {
        $abstract = 'Tanbolt\\Container\\Factory\\SimpleKvInterface';
        $concrete = 'Tanbolt\\Container\\Factory\\SimpleKv';

        $ioc = new Container();
        $ioc->bind('foo', $concrete);
        $a = $ioc->load('foo');
        static::assertTrue($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $b = $ioc->load('foo'));
        static::assertNotSame($a, $b);
        static::assertSame($ioc, $ioc->flush('foo'));
        static::assertFalse($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        try {
            $ioc->load('foo');
            static::fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }

        $ioc = new Container();
        $ioc->bindShared(['foo' => $abstract], $concrete);
        $a = $ioc->load($abstract);
        static::assertTrue($ioc->isBound('foo'));
        static::assertTrue($ioc->isMake('foo'));
        static::assertInstanceOf($concrete, $b = $ioc->load('foo'));
        static::assertSame($a, $b);
        static::assertSame($ioc, $ioc->flush('foo'));
        static::assertFalse($ioc->isBound('foo'));
        static::assertFalse($ioc->isMake('foo'));
        try {
            $ioc->load($abstract);
            static::fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }
        try {
            $ioc->load('foo');
            static::fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }
    }
}


class SimpleClass
{
    public $name;
    public $args;

    public function __construct($name = 'foo')
    {
        $this->name = $name;
        $this->args = func_get_args();
    }
}

class FooClass
{
    public $foo = 'foo';
}

function IocFunction($name = 'ioc')
{
    return $name.':'.join('_', func_get_args());
}

function IocFunctionResolve($name = 'iocResolve', FooClass $foo = null)
{
    $rs = $name;
    if ($foo) {
        $rs .= '_'. $foo->foo;
    }
    if (count($args = func_get_args()) > 2) {
        $rs .= join('_', array_slice($args, 2));
    }
    return $rs;
}

class BarClass
{

    public function IocFunction($name = 'ioc')
    {
        return $name . ':' . join('_', func_get_args());
    }

    public function IocFunctionResolve($name = 'iocResolve', FooClass $foo = null)
    {
        $rs = $name;
        if ($foo) {
            $rs .= '_' . $foo->foo;
        }
        if (count($args = func_get_args()) > 2) {
            $rs .= join('_', array_slice($args, 2));
        }
        return $rs;
    }
}

class BizClass
{
    public static function IocFunction($name = 'ioc')
    {
        return $name.':'.join('_', func_get_args());
    }

    public static function IocFunctionResolve($name = 'iocResolve', FooClass $foo = null)
    {
        $rs = $name;
        if ($foo) {
            $rs .= '_'. $foo->foo;
        }
        if (count($args = func_get_args()) > 2) {
            $rs .= join('_', array_slice($args, 2));
        }
        return $rs;
    }
}

function makeCallbackFunction($object)
{
    $object->set('c', 'c');
}
