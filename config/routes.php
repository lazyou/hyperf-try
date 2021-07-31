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
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

// 路由: 通过配置文件定义;
// 也可 "通过注解来定义路由":
//      1.通过 @AutoController 注解定义路由;
//      2.通过 @Controller 注解定义路由;
Router::get('/test', [\App\Controller\TestController::class, 'test']);

Router::get('/favicon.ico', function () {
    return '';
});
