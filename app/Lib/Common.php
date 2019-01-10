<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 14:34
 */

namespace App\Lib;

use App\Models\Goods;
use App\Models\Reward;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Types\Self_;

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
            case ReleaseEnum::REWARD_ORDER:
                $table = 'pro_mall_reward_order';
                break;
            default:
                $table = '';
                break;
        }
        return $table;
    }


    /**
     * 判断悬赏任务是否全部完成
     * @param $rid
     * @return bool
     */
    public static function Is_Completed($rid){
        try{
            $reward =  Reward::find($rid);
            if(empty($reward))
                return false;
            if($reward->amount == 0){
                $count = Task::where('r_id',$rid)->where('status','>',0)->where('status','<',3)->count();
                if($count<=0){
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }


    /**
     * 短信发送
     * @param $tel
     * @param $code
     * @return mixed
     */
    public static function Send_Message($tel,$code){
        //key
        $key = 'yNUouIrq8028';
        //用户id
        $userid = '9d1964bcc5984ae2b8f522979279ecc3';
        //请求地址
        $url = 'http://apisms.kuaidi100.com:9502/sms/send.do';
        //请求参数
        $post_data = array();
        $post_data['sign']=strtoupper(md5($key.$userid));
        $post_data['userid']=$userid;
        $post_data['seller']='猿玛科技';
        $post_data['phone']=$tel;
        $post_data['tid']=2392;
        $post_data['content']="{'code':".$code."}";
        //post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $result = curl_exec($ch);
        $data = str_replace("\"",'"',$result );
        $data = json_decode($data,true);
        //如果失败，写入日志
        if($data['status']==0){
            Log::error('短信错误：'.$data['msg']);
        }
        return $data;
    }


    /**
     * 快递查询
     * @param $com
     * @param $num
     * @return mixed
     */
    public static function Find_Express($com,$num){
        //参数设置
        $post_data = array();
        $post_data["customer"] = 'B462DBF487B84144EBACAB0A0934ADC7';
        $key= 'yNUouIrq8028' ;
        $post_data["param"] = "{'com':'".$com."','num':'".$num."'}";

        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o="";
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
        }
        $post_data=substr($o,0,-1);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $result = curl_exec($ch);
        $data = str_replace("\"",'"',$result );
        $data = json_decode($data,true);
        return $data;
    }


    /**
     * 生成流水号
     * @return string
     */
    public static function CreateCode(){
        return date('ymd').substr(time(),-5).substr(microtime(),2,5);
    }

    /**
     * 转卖订单，生成订单信息
     * @param $arr
     * @param $order
     * @param $turn_id
     */
    public static function Order_Arr(&$arr,$order,$turn_id){
        $front_Goods = Goods::find($turn_id);
        if(!empty($front_Goods)){
            $temp = [
                'sn' => $order['sn'],
                'type'=>$order['type'],
                'g_id'=>$front_Goods->id,
                'is_turn'=>$front_Goods->type,
                'g_uid'=>$front_Goods->uid,
                'num'=>$order['num'],
                'g_amount'=>$order['g_amount'],
                'fare'=>$order['fare'],
                'total'=>$order['total'],
                'purse'=>$order['purse'],
                'pay_amount'=>$order['pay_amount'],
                'address'=>$order['address'],
                'buy_uid'=>$order['buy_uid'],
                'pay_sn'=>$order['pay_sn']
            ];
            $arr[] = $temp;
            if($front_Goods->type == DefaultEnum::YES){
                self::Order_Arr($arr,$temp,$front_Goods->turn_id);
            }
        }
    }


    /**
     * 获取商品各个类型数量
     * @param $uid
     * @param $status
     * @param $type
     * @return int
     */
    public static function GetOrderNum($uid,$status,$type){
        if($type == 1){ //我卖出的商品，数量
            return DB::table('view_order_list')
                ->where('g_uid',$uid)
                ->where('status',$status)
                ->count();
        }else{ //我买到的商品，数量
            return DB::table('view_order_list')
                ->where('buy_uid',$uid)
                ->where('status',$status)
                ->where('is_turn',0)
                ->where('isdelete',0)
                ->count();
        }
    }

}