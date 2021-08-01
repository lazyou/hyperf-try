# 学习
* https://course.swoole-cloud.com/


# 编程须知
* 不能通过全局变量获取属性参数:
    * __无法__ 通过 `$_GET/$_POST/$_REQUEST/$_SESSION/$_COOKIE/$_SERVER` 等 `$_` 开头的变量获取到任何属性参数

* 通过容器获取的类都是单例:
    * 通过依赖注入容器获取的都是进程内持久化的，是多个协程共享的;
    * 所以不能包含任何的请求唯一的数据或协程唯一的数据，这类型的数据都通过协程上下文去处理;
    * 具体请仔细阅读 依赖注入 和 协程 章节.

* 项目部署:
    * 线上代码部署时，请务必开启 `scan_cacheable`;

    * 开启此配置后，首次扫描时会生成代理类和注解缓存，再次启动时，则可以直接使用缓存，极大优化内存使用率和启动速度;

    * 因为跳过了扫描阶段，所以会依赖 `Composer Class Map`，故我们必须要执行 `--optimize-autoloader` 优化索引:

    * 综上，线上更新代码，重启项目前，需要执行以下命令
        ```shell
        # 优化 Composer 索引
        composer dump-autoload -o
        
        # 生成代理类和注解缓存
        php bin/hyperf.php
        ```


# 生命周期
* 需要先理解 Swoole 的生命周期

* 请求与协程生命周期:
    * Swoole 在处理每个连接时，会默认创建一个协程去处理，主要体现在 `onRequest、onReceive、onConnect` 事件;

    * 可以理解为 **每个请求都是一个协程**，由于创建协程也是个常规操作，所以 __一个请求协程里面可能会包含很多个协程__， __同一个进程内协程之间是内存共享的__;

    * 但调度顺序是非顺序的，且协程间本质上是相互独立的没有父子关系，所以对每个协程的状态处理都需要通过 __协程上下文__ 来管理.


# 协程 -- https://hyperf.wiki/2.2/#/zh-cn/coroutine
* 概念: __Hyperf__ 是运行于 Swoole 4 的协程和 Swow 协程之上的，这也是 Hyperf 能提供高性能的其中一个很大的因素。

### PHP-FPM 的运作模式
* `PHP-FPM` 是一个多进程的 `FastCGI` 管理程序;

* 客户端发起的请求最先抵达的都是 Nginx，然后 `Nginx` 通过 `FastCGI` 协议将请求转发给 `PHP-FPM` 处理，PHP-FPM 的 `Worker` 进程 会抢占式的获得 CGI 请求进行处理;

* 这个处理指的就是，等待 PHP 脚本的解析，等待业务处理的结果返回，完成后回收子进程，这整个的过程是阻塞等待的，也就意味着 PHP-FPM 的进程数有多少能处理的请求也就是多少;

* 假设 PHP-FPM 有 200 个 Worker 进程，一个请求将耗费 1 秒的时间，那么简单的来说整个服务器理论上最多可以处理的请求也就是 200 个，__QPS__ 即为 200/s;

* 在高并发的场景下，这样的性能往往是不够的，尽管可以利用 Nginx 作为负载均衡配合多台 PHP-FPM 服务器来提供服务;

* 但由于 `PHP-FPM` 的 __阻塞等待__ 的工作模型，__一个请求会占用至少一个 MySQL 连接__，多节点高并发下会产生大量的 MySQL 连接，而 MySQL 的最大连接数默认值为 100，尽管可以修改，但显而易见该模式没法很好的应对高并发的场景。


### 异步非阻塞系统
* 高并发的场景下，异步非阻塞就显得优势明显了，直观的优点就是 __`Worker` 进程__ 不再同步阻塞的去处理一个请求，而是可以同时处理多个请求，无需 I/O 等待，并发能力极强, 可以同时发起或维护大量的请求。

* 那么最直观的缺点大家可能也都知道，就是永无止境的 __回调__，业务逻辑必须在对应的回调函数内实现，如果业务逻辑存在多次的 `I/O` 请求，则会存在很多层的回调函数，

* (看完异步回调型的伪代码) 在复杂的业务场景中回调的层次感和代码结构绝对会让你崩溃，其实不难看出这样的写法有点类似 JavaScript 上的异步方法的写法，而 JavaScript 也为此提供了不少的解决方案（当然方案是源于其它编程语言），如 `Promise`，`yield + generator`, `async/await`
    * Promise 则是对回调的一种封装方式，
    * 而 yield + generator 和 async/await 则需要在代码上显性的增加一些代码语法标记，
    * 这些相对比回调函数来说，不妨都是一些非常不错的解决方案，但是你需要另花时间来理解它的实现机制和语法。

* __Swoole 协程__ 也是对 __异步回调__ 的一种解决方案:
    * 在 PHP 语言下，`Swoole 协程` 与 `yield + generator` 都属于协程的解决方案，协程的解决方案可以使代码以近乎于同步代码的书写方式来书写异步代码;
    * 显性的区别则是 `yield + generator `的协程机制下，__每一处 `I/O` 操作的调用代码__ 都需要在前面加上 `yield` 语法实现协程切换，每一层调用都需要加上，否则会出现意料之外的错误;
    * 而 __Swoole 协程__ 的解决方案对比于此就高明多了，在遇到 `I/O` 时 __底层自动的进行隐式协程切换__，无需添加任何的额外语法，无需在代码前加上 yield，协程切换的过程无声无息，极大的减轻了维护异步系统的心智负担。


### 协程是什么？
* 协程是一种 __轻量级的线程__，由用户代码来调度和管理，而不是由操作系统内核来进行调度，也就是在 __用户态__ 进行
    * 一个非标准的线程实现, 什么时候切换由用户自己来实现, 而不是由操作系统分配 CPU 时间决定

* __Swoole__ 的 __每个 Worker 进程__ 会存在一个 __协程调度器__ 来调度协程
    * 协程切换的时机 就是遇到 `I/O` 操作或代码显性切换时，__进程内以单线程的形式运行协程__，也就意味着 __一个进程内同一时间只会有一个协程__ 在运行且切换时机明确，也就无需处理像多线程编程下的各种 __同步锁__ 的问题。
    
    * 单个协程内的代码运行仍是 __串行__ 的，放在一个 HTTP 协程服务上来理解就是每一个请求是一个协程:
    
    * 举个例子，假设为请求A创建了 __协程A__，为 请求B创建了 __协程B__，那么在处理 __协程A__ 的时候代码跑到了查询 MySQL 的语句上，这个时候 __协程A__ 则会触发协程切换，__协程A__ 就继续等待 `I/O` 设备返回结果，那么此时就会切换到 __协程B__，开始处理 __协程B__ 的逻辑，当又遇到了一个 `I/O` 操作便又触发协程切换，再回过来从 __协程A__ 刚才切走的地方继续执行，如此反复，遇到 `I/O` 操作就切换到另一个协程去继续执行而非一直阻塞等待。

    * 这里可以发现一个问题就是，__协程A__ 的 MySQL 查询操作必须得是一个 __异步非阻塞__ 的操作，否则会由于阻塞导致协程调度器没法切换到另一个协程继续执行，这个也是要在 __协程编程下需要规避的问题__ 之一。

* TODO: 常见的 I/O 操作又哪些呢?


### 协程与普通线程有哪些区别？
* 协程与线程很相似，都有自己的上下文，可以共享全局变量;
  
* 但不同之处在于，在同一时间可以有多个线程处于运行状态, 但 __对于 Swoole 协程来说只能有一个，其它的协程都会处于暂停的状态__;

* 普通线程是 `抢占式` 的，哪个线程能得到资源由操作系统决定，而协程是 `协作式` 的，执行权由用户态自行分配.


# 协程编程注意事项
* __不能存在阻塞代码__:
    * 协程内代码的阻塞会导致协程调度器无法切换到另一个协程继续执行代码，所以我们绝不能在协程内存在阻塞代码; 
        > 假设我们启动了 4 个 Worker 来处理 HTTP 请求（通常启动的 Worker 数量与 CPU 核心数一致或 2 倍），如果代码中存在阻塞，暂且理论的认为每个请求都会阻塞 1 秒，那么系统的 QPS 也将退化为 4/s ，这无疑就是退化成了与 PHP-FPM 类似的情况，所以我们绝对不能在协程中存在阻塞代码。
    
    * 哪些是阻塞代码呢？
        * 大多数你所熟知的 `非Swoole 提供的异步函数` 的 `MySQL、Redis、Memcache、MongoDB、HTTP、Socket` 等客户端，`文件操作、sleep/usleep` 等均为__阻塞函数__;
        * Swoole 提供了 MySQL、PostgreSQL、Redis、HTTP、Socket 的协程客户端可以使用;
        * Swoole 4.1 之后提供了一键协程化的方法 `\Swoole\Runtime::enableCoroutine()`, 将 所有使用 `php_stream` 进行 `socket` 操作均变成协程调度的异步 I/O，可以理解为 __除了curl__ 绝大部分原生的操作都可以适用;


* __不能通过全局变量储存状态__:
    * 在 Swoole 的持久化应用下，一个 `Worker` 内的全局变量是 `Worker` 内共享的，而从协程的介绍我们可以知道同一个 Worker 内还会存在多个协程并存在协程切换，也就 __意味着一个 Worker 会在一个时间周期内同时处理多个协程__（或直接理解为请求）的代码， __也就意味着__ 如果使用了全局变量来储存状态可能会被多个协程所使用，也就是说不同的请求之间可能会混淆数据;
    
    * 这里的全局变量指的是 `$_GET/$_POST/$_REQUEST/$_SESSION/$_COOKIE/$_SERVER` 等 `$_` 开头的变量、`global` 变量，以及 `static` 静态属性;
    
    * 那么当我们需要使用到这些特性时应该怎么办？
        * 对于全局变量，均是跟随着一个 `请求(Request)` 而产生的，而 Hyperf 的 `请求(Request)/响应(Response)` 是由 hyperf/http-message 通过实现 PSR-7 处理的，故所有的全局变量均可以在 `请求(Request) `对象中得到相关的值；
      
    * 对于 `global` 变量和 `static` 变量，在 PHP-FPM 模式下，本质都是存活于一个请求生命周期内的，而在 Hyperf 内因为是 CLI 应用，会存在 __全局周期__ 和 __请求周期(协程周期)__ 两种长生命周期:
        * 全局周期，我们只需要创建一个静态变量供全局调用即可，静态变量意味着在服务启动后，任意协程和代码逻辑均共享此静态变量内的数据，也就意味着存放的数据不能是特别服务于某一个请求或某一个协程；
        
    * 协程周期，由于 Hyperf 会为每个请求自动创建一个协程来处理，那么一个协程周期在此也可以理解为一个请求周期，在协程内，所有的状态数据均应存放于 `Hyperf\Utils\Context` 类中，通过该类的 `get`、`set` 来读取和存储任意结构的数据，这个 `Context(协程上下文)` 类在执行任意协程时读取或存储的数据都是仅限对应的协程的，同时在协程结束时也会 __自动销毁__ 相关的上下文数据。

* 最大协程数限制:
    * 对 Swoole Server 通过 set 方法设置 `max_coroutine` 参数，用于配置一个 Worker 进程最多可存在的协程数量

    * 因为随着 Worker 进程处理的协程数目的增加，其对应占用的内存也会随之增加，为了避免超出 PHP 的 `memory_limit` 限制，请根据实际业务的压测结果设置该值;
      
    * Swoole 的 __默认值__ 为 100000;
    
# 使用协程
### 创建一个协程 
* 通过 `co(callable $callable)` 或 `go(callable $callable)` 函数或 `Hyperf\Utils\Coroutine::create(callable $callable)` 即可创建一个协程，协程内可以使用协程相关的方法和客户端。

### 判断当前是否处于协程环境内
* `Hyperf\Utils\Coroutine::inCoroutine(): bool`

### 获得当前协程的 ID
* `Hyperf\Utils\Coroutine::id(): int`

### Channel 通道
* Channel 可为多生产者协程和多消费者协程模式提供支持;
  
* 底层自动实现了协程的切换和调度;

* Channel 与 PHP 的数组类似，仅占用内存，没有其他额外的资源申请，所有操作均为内存操作，无 `I/O` 消耗，使用方法与 `SplQueue` 队列类似;

* Channel 主要用于 __协程间通讯__，当我们希望从一个协程里返回一些数据到另一个协程时，就可通过 Channel 来进行传递;

* `Channel->push`: 当队列中有其他协程正在等待 `pop` 数据时，自动按顺序唤醒一个消费者协程。当队列已满时自动 `yield` 让出控制权，等待其他协程消费数据;

* `Channel->pop`: 当队列为空时自动 `yield`，等待其他协程生产数据。消费数据后，队列可写入新的数据，自动按顺序唤醒一个生产者协程。

### Defer 特性
* 在 __协程结束时__ (还是函数结束时?) 运行一些代码时，可以通过 `defer(callable $callable)` 函数或 `Hyperf\Coroutine::defer(callable $callable)` 将一段函数以 __栈(stack)__ 的形式储存起来

* __栈(stack)__ 内的函数会在当前协程结束时以 __先进后出__ 的流程逐个执行;

### WaitGroup 特性
* WaitGroup 是基于 `Channel` 衍生出来的一个特性;

* 在 Hyperf 里 (那在Swoole里呢?)，`WaitGroup` 的用途是使得 __主协程__ 一直阻塞等待直到所有相关的 __子协程__ 都已经完成了任务后再继续运行;

* 这里说到的阻塞等待是仅对于 __主协程（即当前协程）__ 来说的，__并不会阻塞当前进程__;

### Parallel 特性
* Parallel 特性是 Hyperf 基于 WaitGroup 特性抽象出来的一个更便捷的使用方法

* 限制 Parallel 最大同时运行的协程数 ...

### Concurrent 协程运行控制
* `Hyperf\Utils\Coroutine\Concurrent` 基于 `Swoole\Coroutine\Channel` 实现，用来控制一个代码块内同时运行的最大协程数量的特性。

### 协程上下文
* 由于 __同一个进程内协程间是内存共享的，但协程的执行/切换是非顺序的__，也就意味着我们很难掌控当前的协程是哪一个

* *(事实上可以，但通常没人这么干)*，所以我们需要在发生协程切换时能够同时切换对应的上下文;

* 在 Hyperf 里实现协程的上下文管理将非常简单:
    * `Hyperf\Utils\Context::set()` 储存一个值到当前协程的上下文中;
    * `Hyperf\Utils\Context::get()` 可从当前协程的上下文中取出以 `$id` 为 `key` 储存的值，如不存在则返回 `$default`;
    * `Hyperf\Utils\Context::has()` 判断当前协程的上下文中是否存在以 $id 为 key 储存的值;
    * `Hyperf\Utils\Context::override()` 比较复杂, 请看文档;
    * 通过这些方法设置和获取的值，都仅限于当前的协程，在协程结束时，对应的上下文也会自动跟随释放掉，无需手动管理，无需担忧内存泄漏的风险

### Swoole Runtime Hook Level
* 如果您需要修改整个项目的 `Runtime Hook` 等级，比如想要支持 CURL 协程 并且 Swoole 版本为 v4.5.4 之前的版本，可以修改这里的代码

# 常见问题 -- https://hyperf.wiki/2.2/#/zh-cn/quick-start/questions
* Swoole 短名未关闭

* 异步队列消息丢失

* 代码不生效:
    * `composer dump-autoload -o`
    * 开发阶段，请不要设置 `scan_cacheable` 为 `true`

* 内存限制太小导致项目无法运行


# Introduction

This is a skeleton application using the Hyperf framework. This application is meant to be used as a starting place for those looking to get their feet wet with Hyperf Framework.

# Requirements

Hyperf has some requirements for the system environment, it can only run under Linux and Mac environment, but due to the development of Docker virtualization technology, Docker for Windows can also be used as the running environment under Windows.

The various versions of Dockerfile have been prepared for you in the [hyperf/hyperf-docker](https://github.com/hyperf/hyperf-docker) project, or directly based on the already built [hyperf/hyperf](https://hub.docker.com/r/hyperf/hyperf) Image to run.

When you don't want to use Docker as the basis for your running environment, you need to make sure that your operating environment meets the following requirements:

- PHP >= 7.3
- Swoole PHP extension >= 4.5，and Disabled `Short Name`
- OpenSSL PHP extension
- JSON PHP extension
- PDO PHP extension （If you need to use MySQL Client）
- Redis PHP extension （If you need to use Redis Client）
- Protobuf PHP extension （If you need to use gRPC Server of Client）

# Installation using Composer

The easiest way to create a new Hyperf project is to use Composer. If you don't have it already installed, then please install as per the documentation.

To create your new Hyperf project:

$ composer create-project hyperf/hyperf-skeleton path/to/install

Once installed, you can run the server immediately using the command below.

$ cd path/to/install
$ php bin/hyperf.php start

This will start the cli-server on port `9501`, and bind it to all network interfaces. You can then visit the site at `http://localhost:9501/`

which will bring up Hyperf default home page.
