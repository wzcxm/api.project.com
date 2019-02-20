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
     * 商品
     */
    const GOODS = 2;
    /**
     * 评论
     */
    const DISCUSS = 3;
    /**
     * 悬赏任务
     */
    const REWARD = 4;

}

/**
 * 错误编码
 * Class ErrorCode
 * @package App\Lib
 */
abstract class ErrorCode{
    /**
     * token错误
     */
    const  TOKEN_ERROR = 100;
    /**
     * 数据不存在或错误
     */
    const DATA_LOGIN = 200;
    /**
     * 执行异常
     */
    const EXCEPTION = 300;
    /**
     * 参数错误或参数值不合法
     */
    const PARAM_ERROR = 400;
    /**
     * 禁止登录
     */
    const NO_LOGIN = 500;

}

/**
 * 悬赏任务订单状态
 * Class RewardOrderStatus
 * @package App\Lib
 */
abstract class TaskStatus{
    /**
     * 申请
     */
    const APPLY = 0;
    /**
     * 采纳
     */
    const ACCEPT = 1;
    /**
     * 提交
     */
    const COMPLY = 2;
    /**
     * 完工
     */
    const COMPLETED = 3;
}

/**
 * 资金流水状态
 * Class FundsEnum
 * @package App\Lib
 */
abstract class FundsEnum{
    /**
     * 购买商品
     */
    const BUY  = 1;
    /**
     * 卖出商品
     */
    const SELL = 2;
    /**
     * 充值
     */
    const RECHARGE = 3;
    /**
     * 提现
     */
    const WITHDRAW =4;
    /**
     * 提成
     */
    const COMMISSION = 5;
    /**
     * 发布任务
     */
    const RELEASE = 6;
    /**
     * 完成任务
     */
    const FINISH = 7;
}