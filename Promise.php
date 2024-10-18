<?php
namespace Tanbolt\Container;

use ReflectionMethod;

/**
 * Class Tanbolt: 包含 Tanbolt 框架约定规则的 IOC 容器
 * @package Tanbolt\Container
 */
class Promise extends Container
{
    /**
     * 约定 namespace 以及其 分隔符
     * @var array
     */
    protected $promiseRule = ['Tanbolt' => ':'];

    /**
     * 已尝试处理过的约定组件缓存
     * @var array
     */
    protected $promiseSolved = [];

    /**
     * 缓存删除的 alias 避免再次加载
     * @var array
     */
    protected $flushAliases = [];

    /**
     * Tanbolt constructor.
     */
    public function __construct()
    {
        $this->alias('container', __CLASS__);
        parent::__construct();
    }

    /**
     * 添加自动加载的约定规则, 免去手动绑定的烦恼, 直接 load 就会自动绑定
     * 默认已经内置 Tanbolt 约定规则:
     *      load('name')  => Tanbolt/Name/Name
     *      load('name:sub')  => Tanbolt/Name/Sub
     *      load('Tanbolt/Name/NameInterface')  => Tanbolt/Name/Name
     *      load('Tanbolt/Name/SubInterface')  => Tanbolt/Name/Sub
     * 可自行新增约定规则，如 `Promise::setPromiseRules ('Vendor', '.')` :
     *      load('name.name')  => Vendor/Name/Name
     *      load('name.sub')  => Vendor/Name/Sub
     *      load('Vendor/Name/NameInterface')  => Vendor/Name/Name
     *      load('Vendor/Name/SubInterface')  => Vendor/Name/Sub
     * @param string $prefix 类的命名空间前缀
     * @param string $split 快速加载的别名分隔符
     * @return $this
     */
    public function setPromiseRules(string $prefix, string $split)
    {
        $prefix = trim($prefix, '\\');
        if ($split) {
            $this->promiseRule[$prefix] = $split;
        } else {
            unset($this->promiseRule[$prefix]);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function getAbstract(string $alias)
    {
        $abstract = $this->aliasName($alias);
        // 已绑定 或 已实例化
        if ($abstract !== $alias || isset($this->makes[$alias])) {
            return $abstract;
        }
        // 已明确移除
        if (array_key_exists($abstract, $this->flushAliases)) {
            return $abstract;
        }
        // 已处理过的约定组件
        if (in_array($abstract, $this->promiseSolved)) {
            return $abstract;
        }
        // 使用 namespace
        if (false !== strpos($abstract, '\\')) {
            return $this->getAbstractByNamespace($abstract);
        }
        // 使用约定规则
        return $this->getAbstractByAlias($abstract);
    }

    /**
     * 通过 namespace 加载约定组件，自动设置别名
     * @param string $namespace
     * @return string
     */
    protected function getAbstractByNamespace(string $namespace)
    {
        // 不符合约定组件 namespace 长度
        $classes = explode('\\', $namespace);
        if (($count = count($classes)) < 3) {
            $this->setPromiseSolved($namespace);
            return $namespace;
        }
        // 不在约定组件范围内
        $rules = $this->promiseRule;
        $prefix = array_shift($classes);
        if (!array_key_exists($prefix, $rules)) {
            $this->setPromiseSolved($namespace);
            return $namespace;
        }
        // 加载所用 namespace 是否为 interface, 得出约定的 abstract
        $isInterface = 'Interface' === substr($namespace, -9);
        if ($isInterface) {
            $abstract = substr($namespace, 0, -9);
            $classes[] = substr(array_pop($classes), 0, -9);
        } else {
            $abstract = $namespace;
        }
        if (3 === $count && 'Tanbolt' === $prefix && $classes[0] === $classes[1]) {
            $alias = lcfirst($classes[0]);
        } else {
            $alias = implode($rules[$prefix], array_map(function($item) {
                return lcfirst($item);
            }, $classes));
        }
        $this->fixPromise($alias, $abstract, $isInterface);
        return $namespace;
    }

    /**
     * 通过 alias 加载约定组件，返回 namespace
     * @param string $alias
     * @return string
     */
    protected function getAbstractByAlias(string $alias)
    {
        $namespace = null;
        if (static::checkClassName($alias)) {
            $abstract = ucfirst($alias);
            $namespace = 'Tanbolt\\'.$abstract.'\\'.$abstract;
        } else {
            $rules = $this->promiseRule;
            foreach ($rules as $key => $val) {
                if (false !== strpos($alias, $val)) {
                    if (static::checkClassName(str_replace($val, '', $alias))) {
                        $namespace = $key . '\\' . implode('\\', array_map(function($item){
                            return ucfirst($item);
                        }, explode($val, $alias)));
                    }
                    break;
                }
            }
        }
        // 不是别名, 缓存后直接返回
        if (!$namespace) {
            $this->setPromiseSolved($alias);
            return $alias;
        }
        // 校验别名是否为约定组件, 并自动绑定
        $this->fixPromise($alias, $namespace, null);
        return $this->aliasName($alias);
    }

    /**
     * 检测是否为约定组件，并自动绑定
     * @param string $alias
     * @param string $abstract
     * @param bool $isInterface
     * @return $this
     */
    protected function fixPromise(string $alias, string $abstract, ?bool $isInterface = false)
    {
        // interface 已绑定过, 只需设置别名即可
        $interface = $abstract . 'Interface';
        if ((null === $isInterface || true === $isInterface) && isset($this->binds[$interface])) {
            return $this->setPromiseFixed($alias, $interface, null, $interface, true);
        }

        // alias 绑定到一个不存在的类上了, 若当前 alias 被设置过, 会被移除, 否则 do nothing
        // 理论上这种情况不会发生, load alias 时若已绑定, 就不会走到约定组件的逻辑内
        if (!class_exists($abstract)) {
            return $this->setPromiseFixed($alias, $interface, $abstract);
        }

        // 约定组件 有 约定interface
        $shared = static::isPromiseShared($abstract);
        if (array_key_exists($interface, class_implements($abstract))) {
            if (false !== $isInterface || !isset($this->binds[$interface])) {
                // 可能是 interface 进行绑定 或 interface还未绑定, 以 interface 进行绑定
                $this->factory($interface, $abstract, null, $shared);
            } elseif ($shared) {
                // interface 已绑定了实现, 则以 abstract 直接绑定未共享组件
                $this->bindShared($abstract);
            }
            // 设置 interface 的别名为 alias
            return $this->setPromiseFixed($alias, $interface, $abstract, $interface, true);
        }

        // 找到了约定 interface, 但该 interface 与 abstract 不匹配, 仅做个记录
        if (true === $isInterface) {
            return $this->setPromiseSolved($abstract);
        }
        // 无 interface 且 共享, 绑定之
        if ($shared) {
            $this->bindShared($abstract);
        }
        if (false === $isInterface || !isset($this->binds[$interface])) {
            return $this->setPromiseFixed($alias, null, $abstract, $abstract, true);
        }
        return $this->setPromiseFixed($alias, $interface, $abstract, $interface, true);
    }

    /**
     * 设置约定组件为已解决状态并缓存，同时设置别名
     * @param string $alias 别名
     * @param ?string $interface 接口类
     * @param ?string $abstract 实现类
     * @param ?string $concrete 别名映射的类名,可能是接口类或实现类,也可能为 null(意味着当前 alias 没有匹配到约定组件)
     * @param bool $fixed
     * @return $this
     */
    protected function setPromiseFixed(
        string $alias,
        string $interface = null,
        string $abstract = null,
        string $concrete = null,
        bool $fixed = false
    ) {
        $this->setPromiseSolved($alias)->setPromiseSolved($interface)->setPromiseSolved($abstract);
        if (!$fixed || $this->isAlias($alias)) {
            return $this;
        }
        $this->alias($concrete, $alias);
        if (isset($this->callback[$alias])) {
            if ($concrete) {
                $this->callback[$concrete] = $this->callback[$alias];
            }
            unset($this->callback[$alias]);
        }
        return $this;
    }

    /**
     * 缓存经过处理的约定组件，以便下次直接使用
     * @param ?string $abstract
     * @return $this
     */
    protected function setPromiseSolved(string $abstract = null)
    {
        if ($abstract && !in_array($abstract, $this->promiseSolved)) {
            $this->promiseSolved[] = $abstract;
        }
        return $this;
    }

    /**
     * 判断是否为符合标准的 PHP Class 名称
     * @param string $class
     * @return int
     */
    protected static function checkClassName(string $class)
    {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class);
    }

    /**
     * 判断约定组件是否为共享组件
     * @param string $abstract
     * @return bool
     * @throws
     */
    protected static function isPromiseShared(string $abstract)
    {
        return method_exists($abstract, '__shared')
            && (new ReflectionMethod($abstract, '__shared'))->isStatic()
            && call_user_func([$abstract, '__shared']);
    }

    /**
     * @inheritdoc
     */
    protected function setAlias(string $alias, string $abstract)
    {
        unset($this->flushAliases[$alias]);
        parent::setAlias($alias, $abstract);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function removeAlias(string $alias)
    {
        $this->flushAliases[$alias] = true;
        parent::removeAlias($alias);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function clearAlias()
    {
        $this->flushAliases = [];
        parent::clearAlias();
        return $this;
    }
}
