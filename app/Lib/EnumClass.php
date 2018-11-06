<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 15:51
 */

namespace App\Lib;


class EnumClass
{

}
/**
 * Class Enum_Square
 * 默认是否枚举
 * @package App\Lib
 */
abstract class DefaultEnum
{
    /**
     * 是
     */
    const YES = 1;
    /**
     * 否
     */
    const NO = 0;
}

/**
 * 访问权限
 * Class AccessEnum
 * @package App\Lib
 */
abstract class AccessEnum
{
    /**
     * 公开的
     */
    const PUBLIC = 1;
    /**
     * 私有的
     */
    const PRIVATE = 2;
    /**
     * 部分可见
     */
    const PARTIAL = 3;
}

/**
 * 业务类型
 * Class ReleaseEnum
 * @package App\Lib
 */
abstract  class ReleaseEnum{
    /**
     * 动态
     */
    const DYNAMIC = 1;
    /**
     * 付费商品
     */
    const GOODS = 2;
    /**
     * 积分商品
     */
    const INTEGRAL = 3;
    /**
     * 悬赏任务
     */
    const REWARD = 4;
    /**
     * 评论
     */
    const DISCUSS = 5;
}

/**
 * 错误编码
 * Class ErrorCode
 * @package App\Lib
 */
abstract class ErrorCode{
    //token错误
    const  TOKEN_ERROR = 100;
    //数据不存在或错误
    const DATA_LOGIN = 200;
    //执行异常
    const EXCEPTION = 300;
    //参数错误或参数值不合法
    const PARAM_ERROR = 400;
    //禁止登录
    const NO_LOGIN = 500;

}