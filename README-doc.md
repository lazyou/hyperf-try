# 配置
* `config` 文件夹内

* server.php 配置:
	* 如需要设置守护进程化，可在 `settings` 中增加 `'daemonize' => 1`，执行 `php bin/hyperf.php start` 后，程序将转入后台作为守护进程运行;


# 注解
* TODO: 原理? 好处在哪?

* 注解的三种应用对象: 类, 方法, 类属性;

* 原生注解(Attributes): PHP 8 增加了 原生注解(Attributes) 的特性, Hyperf 2.2 版本开始也支持了原生注解的写法，文档内的所有注解都可做对应的转换;

* phpstorm 注解插件: https://plugins.jetbrains.com/plugin/7320-php-annotations/


# 依赖注入
* Hyperf 默认采用 `hyperf/di` 作为框架的依赖注入管理容器;

* hyperf/di 是一个强大的用于管理类的依赖关系并完成自动注入的组件;

* 与传统依赖注入容器的区别在于更符合长生命周期的应用使用、提供了 __注解及注解注入__ 的支持、提供了无比强大的 __AOP面向切面编程__ 能力;

* 绑定对象关系:
	* 通过构造方法注入 (调用方也必须是由 DI 创建的对象才能完成自动注入);
	* 通过 `@Inject` 注解注入;
	* 抽象对象注入;
		* 在 `config/autoload/dependencies.php` 内完成关系配置;
	* 更多 ...

* 获取容器对象:
	* DI 容器管理的对象均不能包含 __状态__ 值 (状态 可直接理解为会随着请求而变化的值，事实上在 协程 编程中，这些状态值也是应该存放于 `协程上下文` 中);

  	* `@Inject` 注入覆盖顺序
