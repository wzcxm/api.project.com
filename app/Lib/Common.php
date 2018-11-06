<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 14:34
 */

namespace App\Lib;

use App\Models\Files;
use App\Models\Friend;
use App\Models\Users;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
     * 检查手机或者email是否被注册
     * @param string $type
     * @param $value
     * @return bool
     */
    public static function CheckPhoneOrEmail($type,$value){
        $user = Users::where($type,$value)->count();
        if($user!=0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 创建文件保存数组
     * @param $release_type
     * @param $release_id
     * @param $files
     * @return array
     */
    public static function SaveFiles($release_type,$release_id,$files){
        $file_urls = explode('|',$files);
        $file_arr = array();
        foreach ($file_urls as $url){
            $file_arr[] = ['release_type'=>$release_type,'release_id'=>$release_id,'fileurl'=>$url];
        }
        Files::insert($file_arr);
    }

    /**
     * 自增点赞or评论or转发次数
     * @param $release_type
     * @param $release_id
     * @param $uid
     */
    public static function  Increase($release_type,$release_id,$column){
        $table = 'anysa_mall_dynamic';
        switch ($release_type){
            case ReleaseEnum::DYNAMIC:
                $table = 'anysa_mall_dynamic';
                break;
            case ReleaseEnum::GOODS:
                $table = 'anysa_mall_goods';
                break;
            case ReleaseEnum::INTEGRAL:
                $table = 'anysa_mall_integral_goods';
                break;
            case ReleaseEnum::REWARD:
                $table = 'anysa_mall_reward';
                break;
            case ReleaseEnum::DISCUSS:
                $table = 'anysa_mall_comment';
                break;
            default:
                break;
        }
        //自增次数
        DB::table($table)->where('id',$release_id)->increment($column);
    }

    /**
     * 获取业务的文件数组
     * @param $release_type
     * @param $release_id
     * @return array
     */
    public static function  GetFiles($release_type,$release_id){
        $files = Files::where([['release_type',$release_type],['release_id',$release_id]])->get(['fileurl']);
        if(!empty($files)){
            return collect($files)->pluck('fileurl');
        }
        return [];
    }

    /**
     * 获取业务的评论信息列表
     * @param $release_type
     * @param $release_id
     * @return array|\Illuminate\Support\Collection
     */
    public static function GetComment($release_type,$release_id,$friend_uid){
        $comment = DB::table('v_comment_list')
            ->where('release_type',$release_type)
            ->where('release_id',$release_id)
            ->whereIn('uid',$friend_uid)
            ->get(['id','uid','nickname','head_url','comment','likenum','discussnum','create_time']);
        if(!empty($comment)){
            return $comment;
        }
        return [];
    }

    /**
     * 获取用户的好友uid数组（包含自己的）
     * @param $uid
     * @return mixed
     */
    public static function GetFriendUid($uid){
        return Friend::where('uid',$uid)->pluck('friend_uid')->push($uid);
    }

}