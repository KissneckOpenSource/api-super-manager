<?php

use app\middleware\AdminAuth;
use app\middleware\AdminLog;
use app\middleware\AdminPermission;
use app\middleware\AdminResponse;
use think\facade\Route;
Route::group('admin', function() {
    Route::rule('Login/index', 'admin.Login/index', 'post')->middleware([app\middleware\AdminResponse::class]);
    Route::rule('Login/logout', 'admin.Login/logout', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Menu/changeStatus', 'admin.Menu/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Menu/add', 'admin.Menu/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Menu/edit', 'admin.Menu/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Menu/del', 'admin.Menu/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/getUsers', 'admin.User/getUsers', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/changeStatus', 'admin.User/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/add', 'admin.User/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/edit', 'admin.User/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/del', 'admin.User/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/changeStatus', 'admin.Auth/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/delMember', 'admin.Auth/delMember', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/add', 'admin.Auth/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/edit', 'admin.Auth/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/del', 'admin.Auth/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/getGroups', 'admin.Auth/getGroups', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/getRuleList', 'admin.Auth/getRuleList', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/changeStatus', 'admin.App/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/getAppInfo', 'admin.App/getAppInfo', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/add', 'admin.App/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/edit', 'admin.App/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/del', 'admin.App/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/changeStatus', 'admin.InterfaceList/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/getHash', 'admin.InterfaceList/getHash', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/add', 'admin.InterfaceList/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/get_routes', 'admin.InterfaceList/get_routes', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/createDoc', 'admin.InterfaceList/createDoc', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);

    Route::rule('InterfaceList/edit', 'admin.InterfaceList/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/del', 'admin.InterfaceList/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/request', 'admin.Fields/request', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/response', 'admin.Fields/response', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/add', 'admin.Fields/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/upload', 'admin.Fields/upload', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/edit', 'admin.Fields/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Fields/del', 'admin.Fields/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/add', 'admin.InterfaceGroup/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/edit', 'admin.InterfaceGroup/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/del', 'admin.InterfaceGroup/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/getAll', 'admin.InterfaceGroup/getAll', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/changeStatus', 'admin.InterfaceGroup/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/add', 'admin.AppGroup/add', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/edit', 'admin.AppGroup/edit', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/del', 'admin.AppGroup/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/getAll', 'admin.AppGroup/getAll', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/changeStatus', 'admin.AppGroup/changeStatus', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Menu/index', 'admin.Menu/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/index', 'admin.User/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/index', 'admin.Auth/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/index', 'admin.App/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('AppGroup/index', 'admin.AppGroup/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/index', 'admin.InterfaceList/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceGroup/index', 'admin.InterfaceGroup/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Log/index', 'admin.Log/index', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Log/del', 'admin.Log/del', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/refresh', 'admin.InterfaceList/refresh', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Index/upload', 'admin.Index/upload', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('User/own', 'admin.User/own', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('App/refreshAppSecret', 'admin.App/refreshAppSecret', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Login/getUserInfo', 'admin.Login/getUserInfo', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminResponse::class]);
    Route::rule('Auth/editRule', 'admin.Auth/editRule', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('Login/getAccessMenu', 'admin.Login/getAccessMenu', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/group', 'admin.InterfaceList/group', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);
    Route::rule('InterfaceList/createFunc', 'admin.InterfaceList/createFunc', 'post')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);

    Route::rule('App/getAll','admin.App/getAll', 'get')->middleware([app\middleware\AdminAuth::class, app\middleware\AdminPermission::class, app\middleware\AdminLog::class, app\middleware\AdminResponse::class]);


    Route::miss('admin.Miss/index');

});
