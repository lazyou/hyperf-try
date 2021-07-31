<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Controller;

use App\Service\TestService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject; // 使用 @Inject 注解时需要这个命名空间

class TestController extends AbstractController
{
    /**
     * @Inject()
     * @var TestService
     */
    private $testService;

    // 依赖自动注入: 1. 通过构造函数注入;  2. 通过 @Inject 注解注入
    // 好处: 当依赖同时又存在很多其它的依赖时, 就无须手动管理这些依赖，只需要声明一下最终使用的类即可.

//    // 在构造函数声明参数的类型，Hyperf 会自动注入对应的对象或值
//    public function __construct(TestService $testService)
//    {
//        $this->testService = $testService;
//    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    public function test(RequestInterface $request): array
    {
        $id = $request->input('id', 'null');
        $method = $this->request->getMethod();

        return [
            'id' => $id,
            'method' => $method,
            'info' => $this->testService->getInfoById((int) $id),
        ];
    }
}
