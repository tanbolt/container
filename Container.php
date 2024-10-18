<?php
namespace Tanbolt\Container;

use Closure;
use Exception;
use ArrayAccess;
use ErrorException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use BadMethodCallException;
use InvalidArgumentException;
use ReflectionFunctionAbstract;

/**
 * Class Container: Ioc 容器类，用于快速实例化组件，或替换组件。
 * @package Tanbolt\Container
 *
 * @see Container::factory 高灵活度工厂映射
 *
 * @see Container::bind  工厂映射快捷方式
 * @see Container::bindShared
 * @see Container::bindIf
 *
 * @see Container::extend 直接挂载对象
 *
 * @see Container::alias 别名相关
 *
 * @see Container::once 实例化
 * @see Container::load
 * @see Container::loadByArray
 *
 * @see Container::call 工具函数
 */
class Container implements ContainerInterface, ArrayAccess
{
    /**
     * 当前实例
     * @var $this
     */
    protected static $instance;

    /**
     * 组件 alias 别名容器
     * [
     *     (string) alias => (string) abstract
     * ]
     * @var array
     */
    protected $aliases = [];

    /**
     * 组件容器
     * [
     *    (string) abstract => [(callable) concrete, (bool) shared],
     * ]
     * @var array
     */
    protected $binds = [];

    /**
     * 已创建组件容器
     * [
     *      (string) abstract => mixed
     * ]
     * @var array
     */
    protected $makes = [];

    /**
     * 组件 callback 配置容器
     * @var array
     */
    protected $callback = [];

    /**
     * 全局 callback 容器
     * @var array
     */
    protected $globalCall = [];

    /**
     * @inheritDoc
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * 去除 alias binds makes 命名空间开头反斜杠
     * @param string $abstract
     * @return string
     */
    protected static function normalize(string $abstract)
    {
        return ltrim($abstract, '\\');
    }

    /**
     * Container constructor.
     */
    public function __construct()
    {
        static::$instance = $this;
        $this->alias('container', __CLASS__)->alias('container', __CLASS__.'Interface');
    }

    /**
     * 添加一个组件，bind / bindShared / bindIf 同样适用。
     * 1. interface: 组件实现的接口，concrete: 创建组件的方法；ex：
     *      - $ico->factory(interface, concrete);
     *      - $ico->factory([alias => interface], concrete); #同时指定别名
     * 2. abstract: 原组件，concrete: 替换原组件的方法；ex：
     *      - $ico->factory(abstract, concrete);
     *      - $ico->factory([alias => abstract], concrete); #同时指定别名
     * 3. 直接绑定一个组件 (如果不是共享组件且不需别名，没必要，因为直接 load 就可以加载了，没必要这样绑定一下)；ex：
     *      - $ico->factory(abstract, null);
     *      -   $ico->factory([alias => abstract], null); #同时指定别名
     * 4. 备注: alias, interface, abstract: string； concrete: 支持类型 参考 call() 函数 $abstract 的四种类型
     *
     * @param string|array $abstract 绑定名称/接口/别名
     * @param Closure|string|array|null $concrete 实例化方法/对象
     * @param Closure|string|array|null $callback 实例创建后的回调函数
     * @param bool $shared  是否为共享组件
     * @return $this
     */
    public function factory($abstract, $concrete = null, $callback = null, bool $shared = false)
    {
        // 获取绑定组件的名称(若有别名,同时设置别名)
        $abstract = $this->abstractSet($abstract);
        // 清除已实例化的同名对象, 清除别名为 $abstract 的项
        unset($this->makes[$abstract]);
        $this->removeAlias($abstract);
        if ($callback) {
            $this->abstractCallback($abstract, $callback, true);
        }
        return $this->abstractCreate($abstract, $concrete, $shared);
    }

    /**
     * 若组件名为 [$abstract => $alias]，同时设定别名
     * @param array|string $abstract
     * @return string
     * @throws
     */
    protected function abstractSet($abstract)
    {
        $abstract = static::abstractName($abstract, $alias);
        if ($alias) {
            $this->setAlias($alias, $abstract);
        }
        return $abstract;
    }

    /**
     * 获取 abstract 和 alias
     * @param array|string $abstract
     * @param ?string $alias
     * @return string
     */
    protected static function abstractName($abstract, string &$alias = null)
    {
        if (is_array($abstract)) {
            list($alias, $abstract) = [key($abstract), current($abstract)];
        }
        if (empty($abstract)) {
            throw new InvalidArgumentException("Argument abstract can not be empty.");
        }
        $abstract = static::normalize($abstract);
        if ($alias) {
            $alias = static::normalize($alias);
            if ($alias === $abstract) {
                $alias = null;
            }
        }
        return $abstract;
    }

    /**
     * 新增一个 abstract 回调事件
     * @param string $abstract
     * @param Closure|string|array $callback
     * @param bool $prepend
     * @return $this
     */
    protected function abstractCallback(string $abstract, $callback, bool $prepend = false)
    {
        if ($abstract && $callback) {
            if (!isset($this->callback[$abstract])) {
                $this->callback[$abstract] = [];
            }
            if ($prepend) {
                array_unshift($this->callback[$abstract], $callback);
            } else {
                $this->callback[$abstract][] = $callback;
            }
        }
        return $this;
    }

    /**
     * 添加 abstract 到 binds 容器
     * @param string $abstract
     * @param Closure|string|array|null $concrete
     * @param bool $shared
     * @return $this
     */
    protected function abstractCreate(string $abstract, $concrete = null, bool $shared = false)
    {
        if (!$concrete) {
            $concrete = $abstract;
        } elseif (is_string($concrete)) {
            $concrete = static::normalize($concrete);
        }
        $this->binds[$abstract] = compact('concrete', 'shared');
        return $this;
    }

    /**
     * @inheritdoc
     * @see factory
     */
    public function bind($abstract, $concrete = null)
    {
        return $this->factory($abstract, $concrete);
    }

    /**
     * @inheritdoc
     * @see factory
     */
    public function bindShared($abstract, $concrete = null)
    {
        return $this->factory($abstract, $concrete, null, true);
    }

    /**
     * @inheritdoc
     * @throws
     * @see factory
     */
    public function bindIf($abstract, $concrete = null, bool $shared = false)
    {
        if (!is_array($abstract)) {
            if (!$this->isAlias($abstract) && !$this->haveBound($abstract)) {
                $this->factory($abstract, $concrete, null, $shared);
            }
            return $this;
        }
        $abstract = static::abstractName($abstract, $alias);
        if ($alias && $this->isAlias($alias)) {
            return $this;
        }
        $this->setAlias($alias, $abstract);
        if (!$this->haveBound($abstract)) {
            return $this->abstractCreate($abstract, $concrete, $shared);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function extend($abstract, $object)
    {
        $abstract = $this->abstractSet($abstract);
        // clear 已经 Build 的实例, 但不解除 binds alias 中的同名组件
        // 之后若再次 clear objects, 那么 load 时就会用到 binds 或 alias
        $this->clear($abstract);
        $this->makes[$abstract] = $object;
        return $this;
    }

    /**
     * 检查组件是否注册，可使用别名
     * @param string $abstract 待检测组件名
     * @return bool
     */
    public function isBound(string $abstract)
    {
        return $this->checkIfHave($abstract, [$this, 'haveBound']);
    }

    /**
     * 检测组件是否已绑定或注册
     * @param string $abstract 待检测组件名
     * @return bool
     */
    protected function haveBound(string $abstract)
    {
        return isset($this->makes[$abstract]) || isset($this->binds[$abstract]);
    }

    /**
     * 检查组件是否已绑定且为共享组件
     * @param string $abstract 待检测组件名
     * @return bool
     */
    public function isShared(string $abstract)
    {
        return $this->checkIfHave($abstract, function($name) {
            if (isset($this->makes[$name])) {
                return true;
            }
            if (isset($this->binds[$name])) {
                return (bool) $this->binds[$name]['shared'];
            }
            return false;
        });
    }

    /**
     * 由别名递归查询是否已存在
     * @param string $abstract
     * @param callable $check
     * @return bool
     */
    protected function checkIfHave(string $abstract, callable $check)
    {
        $abstract = static::normalize($abstract);
        if ($check($abstract)) {
            return true;
        }
        if (($concrete = $this->getAlias($abstract)) !== $abstract) {
            return call_user_func($check, $concrete);
        }
        return false;
    }

    /**
     * 获取全部已绑定组件
     * @return array
     */
    public function binds()
    {
        return $this->binds;
    }

    /**
     * @inheritdoc
     * @throws
     */
    public function alias(string $abstract, string $alias)
    {
        $alias = static::normalize($alias);
        if (empty($alias)) {
            return $this;
        }
        if (empty($abstract)) {
            $this->removeAlias($alias);
        } else {
            $this->setAlias($alias, static::normalize($abstract));
        }
        return $this;
    }

    /**
     * 缓存别名
     * @param string $alias
     * @param string $abstract
     * @return $this
     * @throws Exception
     */
    protected function setAlias(string $alias, string $abstract)
    {
        if ('container' === $alias) {
            throw new Exception('[container] prohibited from being used in alias.');
        }
        if ($alias !== $abstract) {
            $this->aliases[$alias] = $abstract;
        }
        return $this;
    }

    /**
     * 检测是否为已绑定的别名
     * @param string $alias 要检测的别名
     * @return bool
     */
    public function isAlias(string $alias)
    {
        return isset($this->aliases[static::normalize($alias)]);
    }

    /**
     * 获取别名对应的组件原名
     * @param string $alias 别名
     * @return string
     */
    public function aliasName(string $alias)
    {
        return $this->getAlias(static::normalize($alias));
    }

    /**
     * 获取别名的原名
     * @param string $alias
     * @return string
     */
    protected function getAlias(string $alias)
    {
        if (!isset($this->aliases[$alias])) {
            return $alias;
        }
        return $this->getAlias($this->aliases[$alias]);
    }

    /**
     * 移除别名缓存
     * @param string $alias
     * @return $this
     */
    protected function removeAlias(string $alias)
    {
        unset($this->aliases[$alias]);
        return $this;
    }

    /**
     * 获取全部已设置的别名
     * @return array
     */
    public function aliases()
    {
        return $this->aliases;
    }

    /**
     * 添加全局 或 指定对象的回调函数。
     * - 全局: onMake(callable);
     * - 指定: onMake('abstract', callable);
     * @param Closure|array|string $abstract
     * @param Closure|array|string|null $callback
     * @return $this
     */
    public function onMake($abstract, $callback = null)
    {
        if ($callback) {
            return $this->abstractCallback($this->aliasName($abstract), $callback);
        }
        $this->globalCall[] = $abstract;
        return $this;
    }

    /**
     * 加载一个组件，参考 call() 基本类似，但使用 IOC 容器配置实例化。
     * - 若 $abstract 是 callable 类型，与 call 函数完全相同；
     * - 若 $abstract 是 string: "className"、"className@methodName"、"className::staticMethod"
     *      - load() 函数则会先从容器的别名和绑定设置中寻找是否有具体的落地方法，若有，直接执行；
     *      - call() 函数会直接尝试实例化 className 类，所以 call() 不会直接使用共享组件；
     * - 实例化之后：load() 函数会触发 onMake() 回调函数，call() 不会。
     * - 注：可以将 call() 函数理解为一个工具函数，只是利用了容器自动解决参数依赖。
     * @inheritdoc
     * @see call
     */
    public function load($abstract, ...$args)
    {
        return $this->loadByArray($abstract, $args);
    }

    /**
     * 与 load() 相似，加载一个组件，仅使用一次。
     * - 意味着：
     *    即使共享组件，也不会从容器缓存加载，且加载后不会存储到容器；
     * - 需注意：
     *    若当前加载的组件实例化依赖其他组件，且未手工指定，容器会自动创建依赖组件，若依赖组件是共享组件的话，创建后会挂载到容器上；
     * @inheritdoc
     * @see load
     */
    public function once($abstract, ...$args)
    {
        return $this->loadByArray($abstract, $args, true);
    }

    /**
     * 加载一个组件，与 load() 方法相同，但使用数组作为参数
     * @param Closure|string|array $abstract 函数或类的名称
     * @param array $parameters 函数或类所需参数
     * @param bool $once 是否为一次性使用（即不使用共享组件）
     * @return mixed
     * @throws
     * @see load
     */
    public function loadByArray($abstract, array $parameters = [], bool $once = false)
    {
        // 数组 或 存在 "@/::" 的字符串, 是调用实例方法的
        if (!is_string($abstract) || false !== strpos($abstract, '@') || false !== strpos($abstract, '::')) {
            return $this->resolvedContainer($abstract, $parameters, true);
        }

        // $abstract 可能是别名, 获取最终绑定
        $abstract = $this->getAbstract($abstract);

        // 就是 IOC 对象
        if ('container' === $abstract) {
            return $this;
        }

        // 刚好就是 已 build 的共享实例, 直接返回
        if (!$once && isset($this->makes[$abstract])) {
            $object = $this->makes[$abstract];
            if (count($parameters) && method_exists($object, '__reset')) {
                $this->callByArray([$object, '__reset'], $parameters);
            }
            return $object;
        }

        // build 实例
        $concrete = $this->getConcrete($abstract);
        if (!is_string($concrete) || $abstract === $concrete) {
            $object = $this->resolvedContainer($concrete, $parameters);
        } else {
            $object = $this->resolvedContainer(function () use ($concrete, $parameters) {
                return $this->loadByArray($concrete, $parameters);
            }, $parameters);
        }

        // 回调 / 缓存共享组件
        if (is_string($abstract)) {
            $this->fireCallback($abstract, $object);
            if (!$once && isset($this->binds[$abstract]) && $this->binds[$abstract]['shared']) {
                $this->makes[$abstract] = $object;
            }
        }
        return $object;
    }

    /**
     * 获取加载组件的最终名称
     * @param string $alias
     * @return string
     */
    protected function getAbstract(string $alias)
    {
        return $this->aliasName($alias);
    }

    /**
     * 获取加载组件的创建方法
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete(string $abstract)
    {
        if (isset($this->binds[$abstract])) {
            return $this->binds[$abstract]['concrete'];
        }
        return $abstract;
    }

    /**
     * 触发回调函数
     * @param string $abstract
     * @param mixed $object
     * @return $this
     */
    protected function fireCallback(string $abstract, $object)
    {
        $this->loadCallback($this->globalCall, $object);
        if (isset($this->callback[$abstract])) {
            $this->loadCallback($this->callback[$abstract], $object);
        }
        return $this;
    }

    /**
     * 执行回调函数
     * @param array $callbacks
     * @param mixed $object
     * @return $this
     */
    protected function loadCallback(array $callbacks, $object)
    {
        foreach ($callbacks as $callback) {
            $this->callByArray($callback, [$object, $this]);
        }
        return $this;
    }

    /**
     * 检查组件是否已创建
     * @param string $abstract 要检测的组件名
     * @return bool
     */
    public function isMake(string $abstract)
    {
        return $this->checkIfHave($abstract, function($name) {
            return isset($this->makes[$name]);
        });
    }

    /**
     * 获取全部已创建组件
     * @return array
     */
    public function makes()
    {
        return $this->makes;
    }

    /**
     * @inheritdoc
     */
    public function clear(string $abstract = null)
    {
        if (null === $abstract) {
            $this->makes = [];
            return $this;
        }
        $concrete = static::normalize($abstract);
        if (isset($this->makes[$concrete])) {
            unset($this->makes[$concrete]);
        } else {
            $concrete = $this->getAlias($abstract);
            if ($concrete !== $abstract) {
                $this->clear($concrete);
            }
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function flush(string $abstract = null)
    {
        if (null === $abstract) {
            $this->binds = [];
            $this->makes = [];
            return $this->clearAlias();
        }
        // 清除已绑定/已创建
        $abstract = static::normalize($abstract);
        unset($this->binds[$abstract], $this->makes[$abstract]);

        // 清除别名
        if (isset($this->aliases[$abstract])) {
            $this->flush($this->aliases[$abstract]);
        }
        return $this->removeAlias($abstract);
    }

    /**
     * 清空别名缓存
     * @return $this
     */
    protected function clearAlias()
    {
        $this->aliases = [];
        return $this;
    }

    /**
     * 加载一个函数或者类，若类或函数的参数明确规定了是另外一个类对象，且执行时未手工指明对象，会尝试从容器中自动解决。
     *  1. 执行函数，参数为 callable 类型：`Closure`、`functionName`、`[object, method]`
     *  2. 执行类的静态方法：参数为 `"className::staticMethod"`, 相当于 callable 函数 `[className, staticMethod]`
     *  3. 执行类方法：参数为 `"className@methodName"`，会先自动创建类，再调用指定的类方法
     *  4. 实例化一个雷，第一个参数指定 `"className"`, 后面为创建类所需参数
     *
     * $args 为类或函数所需的参数
     * - 特性一： 当传递参数的个数 大于 函数所需参数个数时, 所有参数都会被传递进函数
     *   > 如 回调函数为 `function(){}` 不需要参数，
     *   但 `call(function, 'foo', 'bar')` 在 `function()` 函数内部通过 `func_get_args` 仍可获取到传入参数
     *
     * - 特性二：若类或函数的参数明确规定了是另外一个类对象, 且执行时未手工指明对象, 会尝试从容器中自动解决
     *   > 如回调函数为 `function(FooClass $foo = null){}` 需要参数，
     *   `call(function)` 会自动从容器中尝试创建 FooClass 并传递给函数。
     *   `call(function, new FooClass())` 会使用指定的 FooClass 传递给函数。
     *
     * @inheritdoc
     * @see load
     */
    public function call($abstract, ...$args)
    {
        return $this->callByArray($abstract, $args);
    }

    /**
     * 加载一个函数或者类，与 call() 方法相同，但使用数组作为参数
     * @param Closure|array|string $abstract 函数或类的名称
     * @param array $parameters 函数或类所需参数
     * @return mixed
     * @throws
     * @see call
     */
    public function callByArray($abstract, array $parameters = [])
    {
        return $this->resolvedContainer($abstract, $parameters);
    }

    /**
     * 返回 load 或 call 的结果
     * @param Closure|string|array $abstract
     * @param array $parameters
     * @param bool $checkBind
     * @return mixed
     * @throws Exception
     */
    protected function resolvedContainer($abstract, array $parameters = [], bool $checkBind = false)
    {
        $abstract = $this->resolvedAbstract($abstract, $checkBind, $constructor);
        if ($constructor) {
            return $this->resolvedConstructor($abstract, $parameters);
        }
        return $this->resolvedCallMethod($abstract, $parameters);
    }

    /**
     * 处理要加载的函数或类
     * @param Closure|string|array $abstract
     * @param bool $checkBind
     * @param ?bool $constructor
     * @return array|string
     * @throws Exception
     */
    protected function resolvedAbstract($abstract, bool $checkBind, ?bool &$constructor = false)
    {
        if (is_array($abstract) || $abstract instanceof Closure) {
            return $abstract;
        }
        if (!is_string($abstract)) {
            throw new BadMethodCallException('Invalid parameter, must be string, array or callable');
        }
        if (false !== strpos($abstract, '::')) {
            return static::resolvedAbstractMethod($abstract);
        }
        if (false !== strpos($abstract, '@')) {
            $segments = static::resolvedAbstractMethod($abstract, '@');
            return [
                $checkBind ? $this->loadByArray($segments[0]) : $this->callByArray($segments[0]),
                $segments[1]
            ];
        }
        if (!function_exists($abstract)) {
            $constructor = true;
        }
        return $abstract;
    }

    /**
     * @param Closure|string|array $abstract
     * @param string $split
     * @return array
     */
    protected static function resolvedAbstractMethod($abstract, string $split = '::')
    {
        $segments = explode($split, $abstract);
        if (2 !== count($segments) || empty($segments[0]) || empty($segments[1])) {
            throw new BadMethodCallException('Method not provided');
        }
        return $segments;
    }

    /**
     * 创建一个对象
     * @param string $concrete
     * @param array $parameters
     * @return object
     * @throws Exception
     */
    protected function resolvedConstructor(string $concrete, array $parameters = [])
    {
        $reflector = class_exists($concrete) ? new ReflectionClass($concrete) : false;
        if (!$reflector || !$reflector->isInstantiable()) {
            throw new InvalidArgumentException("Target '$concrete' is not instantiable.");
        }
        $constructor = $reflector->getConstructor();
        // 无 constructors 函数意味着无需参数,可直接创建对象返回
        if (null === $constructor) {
            return new $concrete;
        }
        return $reflector->newInstanceArgs($this->resolvedParameters($constructor, $parameters));
    }

    /**
     * 执行一个 callable
     * @param Closure|string|array $callback
     * @param array $parameters
     * @return mixed
     * @throws Exception
     */
    protected function resolvedCallMethod($callback, array $parameters = [])
    {
        if (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflector = new ReflectionFunction($callback);
        }
        return call_user_func_array($callback, $this->resolvedParameters($reflector, $parameters));
    }

    /**
     * 通过 $constructor 和 $parameters 获取尝试处理后的参数集合，若参数有依赖，尝试自动解决
     * @param ReflectionFunctionAbstract $constructor
     * @param array $parameters
     * @return array
     * @throws Exception
     */
    protected function resolvedParameters(ReflectionFunctionAbstract $constructor, array $parameters = [])
    {
        if (!count($dependencies = $constructor->getParameters())) {
            return $parameters;
        }
        $params = [];
        foreach ($parameters as $key => $value) {
            if (is_numeric($key) && isset($dependencies[$key])) {
                unset($parameters[$key]);
                $params[$dependencies[$key]->name] = $value;
            }
        }
        $resolves = [];
        foreach ($dependencies as $depend) {
            if (array_key_exists($depend->name, $params)) {
                $resolves[] = [2, $params[$depend->name]];
            } else {
                $resolves[] = $this->tryResolveParameters($depend);
            }
        }
        $args = [];
        $useArgs = false;
        foreach ($resolves as $resolve) {
            $args[] = $resolve[1];
            // 出现 手工指定 或 自动创建对象的情况, 都需要传递参数
            if ($resolve[0] > 0) {
                $useArgs = true;
            }
        }
        // 给定参数已超出所需, 肯定是需要传递了
        if (count($parameters)) {
            $useArgs = true;
            $args = array_values(array_merge($args, $parameters));
        }
        return $useArgs ? $args : [];
    }

    /**
     * 尝试获取 $parameters 中未指定参数的默认值
     * @param ReflectionParameter $parameter
     * @return array
     * @throws Exception
     */
    protected function tryResolveParameters(ReflectionParameter $parameter)
    {
        $dependency = null;
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $dependency = $type->getName();
            if (null !== $class = $parameter->getDeclaringClass()) {
                if ('self' === $dependency) {
                    $dependency = $class->getName();
                } elseif ('parent' === $dependency && $parent = $class->getParentClass()) {
                    $dependency = $parent->getName();
                }
            }
        }
        if (null === $dependency) {
            try {
                return [0, $parameter->getDefaultValue()];
            } catch (Exception $e) {
                throw new ErrorException(
                    'Unresolvable dependency resolving '.
                    "[$parameter] in class {$parameter->getDeclaringClass()->getName()}"
                );
            }
        } else {
            try {
                return [1, $this->loadByArray($dependency)];
            } catch (Exception $e) {
                if ($parameter->isOptional()) {
                    return [0, $parameter->getDefaultValue()];
                }
                throw $e;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return $this->isAlias($offset) || $this->isBound($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->loadByArray($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        if (! $value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }
        $this->bind($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        $this->flush($offset);
    }

    /**
     * @param mixed $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this[$name]);
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this[$name] = $value;
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this[$name];
    }
}
