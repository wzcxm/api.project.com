<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

//获取验证码
$router->get('/GetCode','UserController@GetCode');
//注册
$router->post('/Register','UserController@Register');
//登录
$router->get('/Login','UserController@Login');

$router->group(['middleware' => 'checktoken'],function() use ($router){
    /**
     * 用户
     */
    //退出登录
    $router->get('/Logout','UserController@Logout');
    //获取用户信息
    $router->get('/GetUserInfo','UserController@GetUserInfo');
    //获取用户钱包信息
    $router->get('/GetUserWallet','UserController@GetUserWallet');
    //修改用户信息
    $router->post('/UpdateUser','UserController@UpdateUser');
    /**
     * 好友
     */
    //获取用户好友
    $router->get('/GetFriends','FriendController@GetFriends');
    //查找好友
    $router->get('/FindFriend','FriendController@FindFriend');
    //添加好友
    $router->post('/AddFriend','FriendController@AddFriend');
    //获取好友申请列表
    $router->get('/GetApply','FriendController@GetApply');
    //同意/拒绝好友
    $router->post('/IsAgree','FriendController@IsAgree');
    //查看好友信息
    $router->get('/GetFriendInfo','FriendController@GetFriendInfo');
    /**
     * 标签
     */
    //获取系统标签
    $router->get('/GetSysLabel','LabelController@GetSysLabel');
    //获取用户标签
    $router->get('/GetUserLabel','LabelController@GetUserLabel');
    //编辑标签(新增/修改)
    $router->post('/EditLabel','LabelController@EditLabel');
    //删除标签
    $router->post('/DeleteLabel','LabelController@DeleteLabel');
    /**
     * 评论、点赞
     */
    //点赞
    $router->post('/Like','CommentController@Like');
    //评论
    $router->post('/Comment','CommentController@Comment');
    //删除评论
    $router->post('/DelComment','CommentController@DelComment');
    /**
     * 普通动态
     */
    //发布/修改
    $router->post('/EditDynamic','DynamicController@EditDynamic');
    //转发
    $router->post('/TurnDynamic','DynamicController@TurnDynamic');
    //动态详情
    $router->get('/GetDynamic','DynamicController@GetDynamic');
    //我的普通动态列表
    $router->get('/MyDynamic','DynamicController@MyDynamic');
    //圈子普通动态
    $router->get('/GetCircleDynamic','DynamicController@GetCircleDynamic');
    //普通动态置顶或取消置顶
    $router->post('/DynamicTopping','DynamicController@DynamicTopping');
    //删除动态
    $router->post('/DelDynamic','DynamicController@DelDynamic');
    /**
     * 我的物流地址
     */
    //编辑物流地址
    $router->post('/EditAddress','AddressController@EditAddress');
    //删除物流地址
    $router->post('/DelAddress','AddressController@DelAddress');
    //获取物流地址信息
    $router->get('/GetAddress','AddressController@GetAddress');
    //获取物流地址列表
    $router->get('/GetAddressList','AddressController@GetAddressList');
    /**
     * 付费商品
     */
    //发布/修改商品
    $router->post('/EditGoods','GoodsController@EditGoods');
    //转卖商品
    $router->post('/TurnGoods','GoodsController@TurnGoods');
    //获取商品详情
    $router->get('/GetGoods','GoodsController@GetGoods');
});