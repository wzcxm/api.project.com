<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 14:34
 */

namespace App\Lib;

use Illuminate\Support\Facades\Cache;
class Common
{
    /**
     * 判断是否是正确的邮箱格式;
     * @param $email
     * @return bool
     */
    public static function IsEmail($email){
        $mode = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/';
        if(preg_match($mode,$email)){
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * 判断是否是正确的手机号格式;
     * @param $telephone
     * @return bool
     */
    public static  function IsTelephone($telephone){
        $mode = "/^1[345678]{1}\d{9}$/";
        if(preg_match($mode,$telephone)){
            return true;
        }else{
            return false;
        }

    }

    /**
     * 检查验证码是否过期
     * @param $key
     * @param $value
     * @return bool
     */
    public static function CheckCode($key,$value){
        if (Cache::has($key)) {
            //获取并删除缓存数据
            $code = Cache::pull($key);
            if($value == $code){
                return true;
            }else{
                return false;
            }
        }else{ //验证码失效
            return false;
        }
    }


    /**
     * 根据业务类型，获取表名
     * @param $release_type
     * @return string
     */
    public static function GetTable($release_type){
        switch ($release_type){
            case ReleaseEnum::DYNAMIC:
                $table = 'pro_mall_dynamic';
                break;
            case ReleaseEnum::GOODS:
                $table = 'pro_mall_goods';
                break;
            case ReleaseEnum::INTEGRAL:
                $table = 'pro_mall_integral_goods';
                break;
            case ReleaseEnum::REWARD:
                $table = 'pro_mall_reward';
                break;
            case ReleaseEnum::DISCUSS:
                $table = 'pro_mall_comment';
                break;
            default:
                $table = '';
                break;
        }
        return $table;
    }



}