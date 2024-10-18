<?php

use PHPUnit\Framework\TestCase;
use Tanbolt\Container\Promise;
use Tanbolt\Container\ContainerInterface;

class PromiseTest extends TestCase
{
    public function setUp():void
    {
        PHPUNIT_LOADER::addDir('Tanbolt\F613437', __DIR__.'/F613437');
        PHPUNIT_LOADER::addDir('B923820', __DIR__.'/B923820');
        parent::setUp();
    }

    public function testInstance()
    {
        $fort = new Promise();
        $this->assertSame($fort, $fort::instance());
        $this->assertSame($fort, Promise::instance());
        $this->assertSame($fort, $fort->load(ContainerInterface::class));
    }

    // 测试约定组件, 调用前, 其状态应该为 未绑定/未处理
    protected function promiseBeforeResolve(Promise $fort, $alias, $abstract)
    {
        $this->assertFalse($fort->isAlias($alias));
        $this->assertFalse($fort->isBound($alias));
        $this->assertFalse($fort->isBound($abstract));
        $this->assertFalse($fort->isShared($alias));
        $this->assertFalse($fort->isShared($abstract));
        $this->assertFalse($fort->isMake($alias));
        $this->assertFalse($fort->isMake($abstract));
    }

    // 测试约定组件, 调用后, 会自动设置别名, 有可能还会自动绑定/挂载共享组件
    protected function promiseAfterResolve(
        Promise $fort,
        $foo,
        $alias,
        $abstract,
        $isBound = false,
        $bindShared = false,
        $sharedInstanced = null
    ) {
        $this->assertTrue($fort->isAlias($alias));
        $this->assertEquals($abstract, $fort->aliasName($alias));

        // 约定组件是否已自动绑定
        if ($isBound) {
            $this->assertTrue($fort->isBound($alias));
            $this->assertTrue($fort->isBound($abstract));
        } else {
            $this->assertFalse($fort->isBound($alias));
            $this->assertFalse($fort->isBound($abstract));
        }

        // 约定组件是否 绑定为 共享组件
        if ($bindShared) {
            $this->assertTrue($fort->isShared($alias));
            $this->assertTrue($fort->isShared($abstract));
        } else {
            $this->assertFalse($fort->isShared($alias));
            $this->assertFalse($fort->isShared($abstract));
        }

        // 绑定的共享约定组件 是否已实例化
        $shared = null === $sharedInstanced ? $bindShared : $sharedInstanced;
        if ($shared) {
            $this->assertTrue($fort->isMake($alias));
            $this->assertTrue($fort->isMake($abstract));
            $this->assertSame($foo, $fort->load($alias));
        } else {
            $this->assertFalse($fort->isMake($alias));
            $this->assertFalse($fort->isMake($abstract));
            $this->assertNotSame($foo, $fort->load($alias));
        }
    }

    // 测试有 Interface 约定组件, 并测试 flush 方法
    protected function promiseWithInterface($alias, $abstract, $concrete, $shared = false)
    {
        // 通过 $alias 实例化组件
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $this->promiseBeforeResolve($fort, $alias, $abstract);
        $foo = $fort->load($alias);
        $this->assertInstanceOf($abstract, $foo);
        $this->assertInstanceOf($concrete, $foo);

        // 使用 alias 实例化之后, 会自动绑定/挂载共享组件
        $this->promiseAfterResolve($fort, $foo, $alias, $abstract, true, $shared);

        // flush
        $this->assertSame($fort, $fort->flush($alias));
        $this->promiseBeforeResolve($fort, $alias, $abstract);

        // flush
        try {
            $fort->load($alias);
            $this->fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }

        try {
            $fort->load($abstract);
            $this->fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }

        $fort->bind([$alias => $abstract], $concrete);
        $this->assertTrue($fort->isAlias($alias));
        $this->assertTrue($fort->isBound($alias));
        $this->assertTrue($fort->isBound($abstract));
        $this->assertFalse($fort->isShared($alias));
        $this->assertFalse($fort->isShared($abstract));
        $this->assertFalse($fort->isMake($alias));
        $this->assertFalse($fort->isMake($abstract));

        $foo = $fort->load($alias);
        $this->assertInstanceOf($abstract, $foo);
        $this->assertInstanceOf($concrete, $foo);
        $this->assertInstanceOf($concrete, $fort->load($abstract));
        $this->assertNotSame($foo, $fort->load($alias));

        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $fort->flush($alias);
        try {
            $fort->load($alias);
            $this->fail('It should be throw exception when target is flush');
        } catch (Exception $e) {
            // do nothing
        }

        // 通过 interface($abstract) 实例化组件, 也会自动绑定/挂载共享组件
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $foo = $fort->load($abstract);
        $this->assertInstanceOf($concrete, $foo);
        $this->promiseAfterResolve($fort, $foo, $alias, $abstract, true, $shared);

        // 直接通过 $concrete 实例化组件, 即使为共享组件也不会缓存, 但会自动绑定 alias/interface($abstract)
        // 因为是以 interface 作为共享组件绑定的, $concrete 并未绑定到 Ioc 容器
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $foo = $fort->load($concrete);
        $this->assertInstanceOf($concrete, $foo);
        $this->promiseAfterResolve($fort, $foo, $alias, $abstract, true, $shared, false);
    }

    // 测试无 Interface 约定组件
    protected function promiseSingle($alias, $abstract, $shared = false)
    {
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $this->promiseBeforeResolve($fort, $alias, $abstract);
        $foo = $fort->load($alias);
        $this->assertInstanceOf($abstract, $foo);

        // 无 interface, 单一 $abstract 不会自动绑定(因为自己绑定自己, 没必要)
        // 但对于共享组件, 实例化之后会缓存, 所以也会判断为已绑定, 所以绑定状态由是否 shared 决定
        $this->promiseAfterResolve($fort, $foo, $alias, $abstract, $shared, $shared);

        // 通过 $abstract 实例化组件, 与通过 alias 实例化效果相同
        // 因为没有 interface, 直接以 $abstract 作为共享组件绑定到了 Ioc 容器
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $foo = $fort->load($abstract);
        $this->assertInstanceOf($abstract, $foo);
        $this->promiseAfterResolve($fort, $foo, $alias, $abstract, $shared, $shared);
    }

    public function testPromiseDefault()
    {
        $alias = 'f613437';
        $abstract = 'Tanbolt\F613437\F613437Interface';
        $concrete = 'Tanbolt\F613437\F613437';
        $this->promiseWithInterface($alias, $abstract, $concrete);

        $alias = 'f613437:single';
        $abstract = 'Tanbolt\F613437\Single';
        $this->promiseSingle($alias, $abstract, false);

        $alias = 'f613437:shared';
        $abstract = 'Tanbolt\F613437\Shared';
        $this->promiseSingle($alias, $abstract, true);
    }

    public function testPromiseCustom()
    {
        $alias = 'foo.foo';
        $abstract = 'B923820\Foo\FooInterface';
        $concrete = 'B923820\Foo\Foo';
        $this->promiseWithInterface($alias, $abstract, $concrete, true);

        $alias = 'foo.bar';
        $abstract = 'B923820\Foo\Bar';
        $this->promiseSingle($alias, $abstract, false);

        $alias = 'foo.biz';
        $abstract = 'B923820\Foo\Biz';
        $this->promiseSingle($alias, $abstract, true);
    }

    public function testPromiseDeep()
    {
        $fort = new Promise();
        $fort->setPromiseRules('B923820', '.');
        $this->assertInstanceOf('Tanbolt\F613437\Sub\Foo', $fort->load('f613437:sub:foo'));
        $this->assertInstanceOf('B923820\Foo\Sub\Foo', $fort->load('foo.sub.foo'));
    }

    public function testPromiseOnMake()
    {
        $fort = new Promise();
        $fort->bind(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('f613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bind(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bind(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bind('Tanbolt\F613437\F613437Interface', 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bind('Tanbolt\F613437\F613437Interface', 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bind('Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
    }

    public function testPromiseOnMakeAuto()
    {
        $fort = new Promise();
        $fort->onMake('f613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('f613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('f613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
    }

    public function testPromiseSharedOnMake()
    {
        $fort = new Promise();
        $fort->bindShared(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('f613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bindShared(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bindShared(['f613437' => 'Tanbolt\F613437\F613437Interface'], 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('f613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bindShared('Tanbolt\F613437\F613437Interface', 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437Interface', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(0, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bindShared('Tanbolt\F613437\F613437Interface', 'Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437Interface')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);

        $fort = new Promise();
        $fort->bindShared('Tanbolt\F613437\F613437');
        $fort->onMake('Tanbolt\F613437\F613437', function($obj) {
            $obj->foo++;
        });
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
        $this->assertEquals(1, $fort->load('Tanbolt\F613437\F613437')->foo);
    }
}
