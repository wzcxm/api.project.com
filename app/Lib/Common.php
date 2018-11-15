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
use App\Models\Like;
use App\Models\Order;
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
     */
    public static function SaveFiles($release_type,$release_id,$files){
        $file_urls = explode('|',$files);
        $file_arr = array();
        foreach ($file_urls as $url){
            $file_arr[] = ['release_type'=>$release_type,'release_id'=>$release_id,'fileurl'=>$url];
        }
        //先清空
        Files::where([['release_type',$release_type],['release_id',$release_id]])->delete();
        //保存文件地址
        Files::insert($file_arr);
    }

    /**
     * 自增点赞or评论or转发次数
     * @param $release_type
     * @param $release_id
     * @param $column
     */
    public static function  Increase($release_type,$release_id,$column){
        $table = self::GetTable($release_type);
        if(!empty($table)){
            //自增次数
            DB::table($table)->where('id',$release_id)->increment($column);
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

    /**
     * 自减点赞or评论or转发次数
     * @param $release_type
     * @param $release_id
     * @param $column
     */
    public static function  Decrement($release_type,$release_id,$column){
        $table = self::GetTable($release_type);
        if(!empty($table)) {
            //自减次数
            DB::table($table)->where('id', $release_id)->decrement($column);
        }
    }

    /**
     * 获取业务的文件地址数组
     * @param $release_type
     * @param $release_id
     * @return array
     */
    public static function  GetFiles($release_type,$release_id){
        $files = Files::where([['release_type',$release_type],['release_id',$release_id]])->get(['fileurl']);
        if(count($files)>0){
            return collect($files)->pluck('fileurl');
        }
        return null;
    }



    /**
     * 获取业务的评论信息列表
     * @param $release_type
     * @param $release_id
     * @return array|\Illuminate\Support\Collection
     */
    public static function GetComment($release_type,$release_id){
        $comment = DB::table('v_comment_list')
            ->where('release_type',$release_type)
            ->where('release_id',$release_id)
            ->get(['id','reply_id','uid','nickname','head_url','comment','likenum','discussnum','create_time']);
        if(count($comment)>0){
            return $comment;
        }
        return null;
    }

    /**
     * 获取用户的好友uid数组（包含自己的）
     * @param $uid
     * @return mixed
     */
    public static function GetFriendUid($uid){
        return Friend::where('uid',$uid)->pluck('friend_uid')->push($uid);
    }


    /**
     * 是否点赞
     * @param $release_type
     * @param $release_id
     * @param $uid
     * @return bool
     */
    public static  function IsLike($release_type,$release_id,$uid){
        $count = Like::where(
            [['release_type',$release_type],
            ['release_id',$release_id],
                ['uid',$uid]])->count();
        if($count>0){
            return true;
        }else{
            return false;
        }
    }


    /**
     * 设置商品返回值
     * @param $ret_arr
     * @param $data
     * @param $type
     * @param $order_type
     */
    public static function SetGoods(&$ret_arr,$data,$type,$order_type){
        $ret_arr['title'] = $data->title; //商品名称
        $ret_arr['remark'] = $data->remark; //描述
        $ret_arr['number'] = $data->number; //库存数量
        $ret_arr['label_name'] = $data->labelInfo->name; //标签
        $ret_arr['address'] = $data->address; //地址
        $ret_arr['price'] = $data->price; //单价
        if(!empty($data->firstprice)){
            $ret_arr['firstprice'] = $data->firstprice; //原价
        }
        $ret_arr['fare'] = $data->fare;  //运费
        $ret_arr['limit'] = $data->limit; //转卖上限
        $ret_arr['sell_num'] = self::GetSellNum($data->id,$order_type);  //销量
        if($data->isannex == DefaultEnum::YES){
            $ret_arr['files'] = self::GetFiles($type,$data->id); //图片地址
        }
    }

    /**
     * 获取商品的销售量
     * @param $orderId
     * @param $type
     * @return mixed
     */
    private static function GetSellNum($orderId,$type){
        return  Order::where('order_type',$type)->where('goods_id',$orderId)->count();
    }

    /**
     * 去除没有查看权限的圈子数据
     * @param $items
     * @param $uid
     */
    public static function FilterRelease(&$items,$uid){
        //去除没有权限的动态
        $items =  array_filter($items,function ($item) use($uid){
            if($item['type']==DefaultEnum::NO){
                if($item['access']==AccessEnum::PUBLIC){
                    return $item;
                }elseif($item['access']==AccessEnum::PRIVATE){
                    if($item['uid'] == $uid){
                        return $item;
                    }
                }elseif($item['access']==AccessEnum::PARTIAL){
                    $arr = json_decode($item['visible_uids'],true);
                    if(in_array($uid,$arr) || $item['uid'] == $uid){
                        return $item;
                    }
                }
            }else{
                return $item;
            }
        });
    }

    /**
     * 给圈子数据添加文件访问地址
     * @param $items
     * @param $release_type
     */
    public static function SetFileUrl(&$items,$release_type){
        //获取文件地址
        $files_id_arr = array_map(function ($item){
            if($item['type'] == DefaultEnum::NO && $item['isannex'] == DefaultEnum::YES){
                return $item['id'];
            }else if($item['type'] == DefaultEnum::YES && $item['init_annex'] == DefaultEnum::YES){
                return $item['init_id'];
            }
        },$items);
        $id_arr = array_filter(array_unique($files_id_arr));
        $files =  Files::where('release_type',$release_type)
            ->whereIn('release_id',$id_arr)
            ->get(['release_id','fileurl']);
        $files = json_decode($files,true);
        //添加文件访问地址
        foreach ($items as &$data){
            if($data['type'] == DefaultEnum::NO && $data['isannex'] == DefaultEnum::YES){
                $id =  $data['id'];
            }else if($data['type'] == DefaultEnum::YES && $data['init_annex'] == DefaultEnum::YES){
                $id =  $data['init_id'];
            }
            if(!empty($id)){
                $data['files'] = array_column(array_filter($files,function ($item) use($id){
                    return $item['release_id'] == $id;
                }),'fileurl');
            }
        }
    }

}