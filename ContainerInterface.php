<?php
namespace Tanbolt\Container;

use Closure;

interface ContainerInterface
{
    /**
     * 设置一个静态调用方法，可以自动初始化类，并保证只初始化一次
     * @return static
     */
    public static function instance();

    /**
     * 添加非共享组件
     * @param string|array $abstract 绑定名称/接口/别名
     * @param Closure|array|string|null $concrete 实例化方法/对象
     * @return static
     */
    public function bind($abstract, $concrete = null);

    /**
     * 添加共享组件
     * @param string|array $abstract 绑定名称/接口/别名
     * @param Closure|array|string|null $concrete 实例化方法/对象
     * @return static
     */
    public function bindShared($abstract, $concrete = null);

    /**
     * 在未添加的前提下添加组件
     * @param string|array $abstract 绑定名称/接口/别名
     * @param Closure|array|string|null $concrete 实例化方法/对象
     * @param bool $shared 是否为共享组件
     * @return static
     */
    public function bindIf($abstract, $concrete = null, bool $shared = false);

    /**
     * 设置组件别名
     * @param string $abstract 类/接口 class
     * @param string $alias 别名
     * @return static
     */
    public function alias(string $abstract, string $alias);

    /**
     * 添加一个已创建的实例
     * @param string|array $abstract 绑定名称/接口/别名
     * @param mixed $object 绑定值
     * @return static
     */
    public function extend($abstract, $object);

    /**
     * 清除(所有/指定)的已实例化组件，清除可再次加载
     * @param ?string $abstract 名称/接口/别名
     * @return static
     */
    public function clear(string $abstract = null);

    /**
     * 卸载(所有/指定)的组件，卸载后无法再次加载
     * @param ?string $abstract 名称/接口/别名
     * @return static
     */
    public function flush(string $abstract = null);

    /**
     * 加载一个函数或者类，使用 IOC 容器配置解决依赖。
     * @param Closure|array|string $abstract 函数或类的名称
     * @param mixed $args 函数或类所需参数
     * @return mixed
     */
    public function load($abstract, ...$args);

    /**
     * 与 load 执行过程完全相同, 唯一的不同之处在于即使为共享组件, 实例化后也不挂载到容器中
     * @param Closure|array|string $abstract 函数或类的名称
     * @param mixed $args 函数或类所需参数
     * @return mixed
     */
    public function once($abstract, ...$args);

    /**
     * 加载一个函数或者类
     * @param Closure|array|string $abstract 函数或类的名称
     * @param mixed $args 函数或类所需参数
     * @return mixed
     */
    public function call($abstract, ...$args);
}
