<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/16
 * Time: 11:21
 */

namespace App\Lib;

use Illuminate\Support\Facades\DB;
class DataComm
{
    /**
     * 获取玩家的好友列表
     * @param $uid
     * @return \Illuminate\Support\Collection
     */
    public static function GetFriends($uid){
        return DB::table('pro_mall_friend as f')
            ->leftJoin('pro_mall_users as u','u.uid','=','f.friend_uid')
            ->where('f.uid',$uid)
            ->select('f.friend_uid','u.nickname','u.head_url','u.telephone')
            ->get();
    }

    /**
     * 获取玩家的好友申请列表
     * @param $uid
     * @return \Illuminate\Support\Collection
     */
    public static function GetApplyList($uid){
        return DB::table('pro_mall_apply as a')
            ->leftJoin('pro_mall_users as u','u.uid','=','a.ask_uid')
            ->where('a.reply_uid',$uid)
            ->select('a.id','a.ask_uid','u.nickname','u.head_url','a.remark','a.status')
            ->get();
    }

    /**
     * 获取用户的好友uid数组（包含自己的）
     * @param $uid
     * @return mixed
     */
    public static function GetFriendUid($uid){
        $friends = DB::table('pro_mall_friend')
            ->where('uid',$uid)
            ->pluck('friend_uid')
            ->push($uid);
        return json_decode($friends,true);
    }


    /**
     * 是否点赞
     * @param $type
     * @param $id
     * @param $uid
     * @return bool
     */
    public static  function IsLike($type,$id,$uid){
        $count = DB::table('pro_mall_like')
            ->where('pro_type',$type)
            ->where(  'pro_id',$id)
            ->where('uid',$uid)->count();
        if($count>0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取业务的评论信息列表
     * @param $type
     * @param $id
     * @return array|\Illuminate\Support\Collection
     */
    public static function GetComment($type,$id){
        return DB::table('view_get_comment')
            ->where('pro_type',$type)
            ->where('pro_id',$id)
            ->select('id','uid','nickname','head_url','comment','img_url','likes','create_time','reply_id')
            ->get();
    }


    /**
     * 保存业务的附件地址
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
        DB::table('pro_mall_files')
            ->where('release_type',$release_type)
            ->where('release_id',$release_id)
            ->delete();
        //保存文件地址
        DB::table('pro_mall_files')->insert($file_arr);
    }

    /**
     * 自增点赞or评论or转发次数
     * @param $type
     * @param $id
     * @param $column
     */
    public static function  Increase($type,$id,$column){
        $table = Common::GetTable($type);
        if(!empty($table)){
            //自增次数
            DB::table($table)->where('id',$id)->increment($column);
        }

    }

    /**
     * 自减点赞or评论or转发次数
     * @param $type
     * @param $id
     * @param $column
     */
    public static function  Decrement($type,$id,$column){
        $table = Common::GetTable($type);
        if(!empty($table)) {
            //自减次数
            DB::table($table)->where('id', $id)->decrement($column);
        }
    }

    /**
     * 检查手机是否被注册
     * @param $tel
     * @return bool
     */
    public static function CheckPhone($tel){
        $user = DB::table('pro_mall_users')->where('telephone',$tel)->count();
        if($user>0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取业务的文件地址，返回数组
     * @param $type
     * @param $id
     * @return array
     */
    public static function  GetFiles($type,$id){
        $files = DB::table('pro_mall_files')
            ->where('release_type',$type)
            ->where('release_id',$id)
            ->get(['fileurl']);
        if(count($files)>0){
            return collect($files)->pluck('fileurl');
        }
        return null;
    }

    /**
     * 获取用户的悬赏任务订单
     * @param $id
     * @param $uid
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetRewardOrder($id,$uid){
        return DB::table('pro_mall_reward_order as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->where('t.reward_id',$id)
            ->where('t.uid',$uid)
            ->select('t.id','t.uid','u.nickname','u.head_url','t.ask','t.isannex','t.status','t.create_time')
            ->first();
    }



    /**
     * 获取动态全部评论信息
     * @param $type
     * @param $id_arr
     * @return \Illuminate\Support\Collection|null
     */
    public static  function GetCommentAll($type,$id_arr){
        $data = DB::table('view_get_comment')
            ->where('pro_type',$type)
            ->whereIn('pro_id',$id_arr)
            ->select('id','nickname','comment')
            ->orderBy('create_time','desc')
            ->get();
        return $data??null;
    }

    /**
     * 给动态添加评论信息
     * @param $items
     * @param $type
     * @param $uid
     */
    public static function SetComment(&$items,$type,$uid){

        $id_arr = array_column($items, 'id');

        foreach ($items as &$data){
            //如果有插入商品或任务，添加商品或任务信息
            if(!empty($data['infix_id'])){
                $data['infix'] = self::GetInfixInfo($data['infix_id'],$data['infix_type']);
            }
            //是否点赞
            $data['islike'] = self::IsLike($type,$data['id'],$uid);
            //添加评论
            $data['comment'] = self::GetCommentThree($type,$data['id']);
        }
    }

    /**
     * 获取一条动态详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetDynamicInfo($id){
        return DB::table('view_get_dynamic')
            ->where('id',$id)
            ->first();
    }

    /**
     * 获取动态列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetDynamicList($id){
        $data = DB::table('view_dynamic_list');
        if(is_array($id)){
            $data = $data->whereIn('uid',$id);
        }else{
            $data = $data->where('uid',$id);
        }
        $data = $data->orderBy('id','desc')->simplePaginate(10);
        return $data??null;
    }

    /**
     * 广场动态列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareDynamicList(){
        $data = DB::table('view_dynamic_list')
            ->where('is_plaza',1)
            ->orderBy('id','desc')
            ->simplePaginate(10);
        return $data??null;
    }

    /**
     * 获取动态插入的商品或任务信息
     * @param $infix_id
     * @param $infix_type
     * @return \Illuminate\Support\Collection|null
     */
    public static function GetInfixInfo($infix_id,$infix_type){
        $infix = DB::select('call pro_get_infix(?,?)',[$infix_type,$infix_id]);
        return $infix??null;
    }



    /**
     * 获取一条付费商品详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetGoodsInfo($id){
        return DB::table('view_get_goods')
            ->where('id',$id)
            ->first();
    }

    /**
     * 获取付费商品列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetGoodsList($id){
        $data = DB::table('view_goods_list');
        if(is_array($id)){
            $data = $data->whereIn('uid',$id);
        }else{
            $data = $data->where('uid',$id);
        }
        $data = $data->orderBy('id','desc')->simplePaginate(10);
        return $data??null;
    }


    /**
     * 广场付费商品列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareGoodsList(){
        $data = DB::table('view_goods_list')
            ->where('is_plaza',1)
            ->orderBy('id','desc')
            ->simplePaginate(10);
        return $data??null;
    }

    /**
     * 发布的商品列表（包含付费商品和积分商品）
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetPostedList($uid){
        //付费商品
        $exp_goods = <<<EOT
            t.id,
            t.uid,
            0 as type,
            t.title,
            t.number,
            t.price,
            t.firstprice,
            t.fare,
            l.name as label_name,
            (select fileurl from pro_mall_files where release_type=2 and release_id=t.id limit 1) as file_url
EOT;
        $goods = DB::table('pro_mall_goods as t')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->where('t.type',0)
            ->where('t.uid',$uid)
            ->selectRaw($exp_goods);
        //积分商品
        $exp_integral = <<<EOT
            i.id,
            i.uid,
            1 as type,
            i.title,
            i.number,
            i.price,
            0 as firstprice,
            i.fare,
            a.name as label_name,
            (select fileurl from pro_mall_files where release_type=3 and release_id=i.id limit 1) as file_url
EOT;
        $integral = DB::table('pro_mall_integral_goods as i')
            ->leftJoin('pro_sys_label as a','a.id','=','i.label')
            ->where('i.type',0)
            ->where('i.uid',$uid)
            ->selectRaw($exp_integral)
            ->unionAll($goods);
        return $integral
            ->orderBy('id','desc')
            ->simplePaginate(10);
    }

    /**
     * 代理的商品列表（包含付费商品和积分商品）
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetProxyList($uid){
        //付费商品
        $exp_goods = <<<EOT
            t.id,
            t.uid,
            0 as type,
            t.turnprice,
            g.isdelete as init_status,
            g.title,
            g.number,
            g.price,
            g.firstprice,
            g.fare,
            l.name as label_name,
            (select fileurl from pro_mall_files where release_type=2 and release_id=g.id limit 1) as file_url
EOT;
        $goods = DB::table('pro_mall_goods as t')
            ->leftJoin('pro_mall_goods as g','g.id','=','t.first_id')
            ->leftJoin('pro_sys_label as l','l.id','=','g.label')
            ->where('t.type',1)
            ->where('t.uid',$uid)
            ->selectRaw($exp_goods);
        //积分商品
        $exp_integral = <<<EOT
            s.id,
            s.uid,
            1 as type,
            s.turnprice,
            i.isdelete as init_status,
            i.title,
            i.number,
            i.price,
            0 as firstprice,
            i.fare,
            a.name as label_name,
            (select fileurl from pro_mall_files where release_type=3 and release_id=i.id limit 1) as file_url
EOT;
        $integral = DB::table('pro_mall_integral_goods as s')
            ->leftJoin('pro_mall_integral_goods as i','i.id','=','s.first_id')
            ->leftJoin('pro_sys_label as a','a.id','=','i.label')
            ->where('s.type',1)
            ->where('s.uid',$uid)
            ->selectRaw($exp_integral)
            ->unionAll($goods);

        return $integral
            ->orderBy('id','desc')
            ->simplePaginate(10);
    }

    /**
     * 获取一条任务信息
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetRewardInfo($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.front_id,
            t.isannex,    
            t.create_time,
            t.title,
            t.remark,
            t.number,
            t.bounty,
            t.price,
            t.hope_time,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            l.name as label_name,
            t.address
EOT;

        return DB::table('pro_mall_reward as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->where('t.id',$id)
            ->selectRaw($expression)
            ->first();
    }

    /**
     * 获取悬赏任务列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetRewardList($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.topping,
            t.create_time,
            t.access,
            t.isannex,
            t.visible_uids,
            t.issquare,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            if(t.type=0,t.title,r.title) as title,
            if(t.type=0,t.remark,r.remark) as remark,
            if(t.type=0,t.bounty,r.bounty) as bounty,
            if(t.type=0,t.number,r.number) as number,
            if(t.type=0,t.hope_time,r.hope_time) as hope_time,
            if(t.type=0,t.price,r.price) as price,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,r.address) as address,
            r.id as init_id,
            r.isannex as init_annex,
            r.uid as init_uid,
            s.nickname as init_nick,
            s.head_url as init_head
EOT;
        $data = DB::table('pro_mall_reward as t')
            ->leftJoin('pro_mall_reward as r','r.id','=','t.front_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_mall_users as s','s.uid','=','r.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','r.label')
            ->where('t.isdelete',0);
        if(is_array($id)){
            $data = $data->whereIn('t.uid',$id);
        }else{
            $data = $data->where('t.uid',$id);
        }
        $data = $data->orderBy('t.id','desc')
            ->selectRaw($expression)
            ->simplePaginate(10);
        return $data;
    }

    /**
     * 广场悬赏任务列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareRewardList(){
        $expression = <<<EOT
                t.id,
                t.uid,
                u.nickname,
                u.head_url,
                t.type,
                t.topping,
                t.create_time,
                t.isannex,
                t.turnnum+t.turnnum_add as turn_num,
                t.likenum+t.likenum_add as like_num,
                t.discussnum+t.discussnum_add as discuss_num,
                t.title,
                t.remark,
                t.bounty,
                t.number,
                t.hope_time,
                t.price,
                l.name as label_name,
                t.address
EOT;
        $data = DB::table('pro_mall_reward as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->where('t.isdelete',0)
            ->where('t.issquare',1)
            ->orderBy('t.id','desc')
            ->selectRaw($expression)
            ->simplePaginate(10);
        return $data;
    }

    /**
     * 获取发布的悬赏任务
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetMyReward($uid){
        $expression = <<<EOT
            t.id,
            t.title,
            t.number,
            t.bounty,
            t.hope_time,
            t.create_time,
            (select fileurl from pro_mall_files where release_type=4 and release_id=t.id limit 1) as file_url,
            (select count(*) from pro_mall_reward_order where reward_id=t.id and isdelete=0 and status=0) as news
EOT;
        return DB::table('pro_mall_reward as t')
            ->where('t.type',0)
            ->where('t.isdelete',0)
            ->where('t.uid',$uid)
            ->selectRaw($expression)
            ->orderBy('t.id','desc')
            ->simplePaginate(10);

    }

    /**
     * 获取申请的悬赏任务
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetMyApplyReward($uid){
        $expression = <<<EOT
            o.id,
            o.reward_id,
            t.title,
            t.bounty,
            t.price,
            t.create_time,
            (select fileurl from pro_mall_files where release_type=4 and release_id=t.id limit 1) as file_url
EOT;
        return DB::table('pro_mall_reward_order as o')
            ->leftJoin('pro_mall_reward as t','t.id','=','o.reward_id')
            ->where('o.isdelete',0)
            ->where('o.uid',$uid)
            ->selectRaw($expression)
            ->orderBy('o.id','desc')
            ->simplePaginate(10);
    }

    /**
     * 获取悬赏任务申请列表
     * @param $id
     * @return \Illuminate\Support\Collection
     */
    public static function GetRewardOrderList($id){
        $expression = <<<EOT
            t.id,
            t.reward_id,
            t.uid, 
            u.nickname,
            u.head_url,
            t.create_time,
            t.status
EOT;
        return DB::table('pro_mall_reward_order  as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->where('t.reward_id',$id)
            ->where('t.isdelete',0)
            ->selectRaw($expression)
            ->orderBy('t.id','desc')
            ->get();

    }
}