<?php
/**
 * 权限相关路由
 * @since   2021-09-09
 * @author  fxl <fengxiaolei@kissneck.cn>
 */

use think\facade\Route;

Route::group('role', function() {
    Route::resource('menu', 'role.Menu');
    //MISS路由定义
    //Route::miss('admin.Miss/index');
})->middleware(app\middleware\AdminResponse::class);