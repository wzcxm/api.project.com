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
     * @param $id
     * @return \Illuminate\Support\Collection|null
     */
    public static  function GetThreeComment($type,$id){
        $data = DB::table('view_get_comment')
            ->where('pro_type',$type)
            ->where('pro_id',$id)
            ->select('id','nickname','comment')
            ->orderBy('create_time','desc')
            ->limit(3)
            ->get();
        return $data??null;
    }

    /**
     * 给动态添加评论信息
     * @param $items
     * @param $type
     */
    public static function SetComment(&$items,$type){
        foreach ($items as &$data){
            //如果有插入商品或任务，添加商品或任务信息
            if(!empty($data['infix_id'])){
                $data['infix_info'] = self::GetInfixInfo($data['infix_id'],$data['infix_type']);
            }
            //添加评论
            $data['comment'] = self::GetThreeComment($type,$data['id']);
        }
    }

    /**
     * 获取动态列表
     * @param $uid
     * @param $type
     * @param $uid_arr
     * @param $page
     * @param $find_str
     * @return array|null
     */
    public static function GetDynamicList($uid,$type,$uid_arr,$find_str,$page){
        $data = DB::select('call pro_dynamic_list(?,?,?,?,?,?)',[$uid,$type,$uid_arr,$find_str,($page-1)*10,10]);
        return $data ?? null;
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
     * 获取付费商品列表
     * @param $param
     * @return array|null
     */
    public static function GetGoodsList($param){
        $sql ='select  t.*,(select count(*) from pro_mall_like where pro_type=2 and pro_id=t.id and uid='.
            $param['uid'].') as is_like from view_goods_list t where 1=1 ';
        //查询数据类型
        if(!empty($param['where'])){
            $sql .= $param['where'];
        }
        //搜索关键字
        if(!empty($param['keyword'])){
            $sql .= " and t.title like '%".$param['keyword']."%' "  ;
        }
        //筛选
        //支付方式
        if($param['pay_type']!=''){
            $sql .= " and t.pay_type=".$param['pay_type']  ;
            //价格区间
            if($param['price_start']!=''  && $param['price_end']!=''){
                $sql .= ' and t.price between '.$param['price_start'].' and '.$param['price_end'] ;
            }
            //发货地址
            if(!empty($param['address'])){
                $sql .= " and t.address like '%".$param['address']."%'" ;
            }
            //标签
            if(!empty($param['label_name'])){
                $sql .= " and t.label_name = '".$param['label_name']."'" ;
            }
        }
        //排序
        $sql .= " order by " .$param['order_by'];
        //分页
        $sql .= ' LIMIT '.(($param['page']-1)*10).',10';
        $data = DB::select($sql);
        //保存关键字
        if(!empty($param['keyword'])){
            $count = DB::table('pro_mall_keyword')
                ->where('keyword','like','%'.$param['keyword'].'%')
                ->count();
            if($count==0){
                DB::table('pro_mall_keyword')->insert(['keyword'=>$param['keyword'],'type'=>2]);
            }
        }
        return $data ?? null;
    }

    /**
     * 我发布的商品列表（包含付费商品和积分商品）
     * @param $uid
     * @return \Illuminate\Support\Collection|null
     */
    public static function GetPostedList($uid){

        $goods = DB::table('pro_mall_goods')
            ->where('type',0)
            ->where('uid',$uid)
            ->where('isdelete',0)
            ->select('id', 'main_url', 'title', 'price', 'pay_type')
            ->simplePaginate(10);
        return $goods ?? null;
    }

    /**
     * 代理的商品列表（包含付费商品和积分商品）
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetProxyList($uid){
        $goods = DB::table('pro_mall_goods as t')
            ->leftJoin('pro_mall_goods as g','g.id','=','t.init_id')
            ->where('t.type',1)
            ->where('t.uid',$uid)
            ->where('t.isdelete',0)
            ->selectRaw('t.id,g.main_url, g.title,t.turn_price,g.pay_type,g.isdelete as is_fail')
            ->orderBy('t.id','desc')
            ->simplePaginate(10);
        return $goods?? null;
    }


    /**
     * 获取悬赏任务列表
     * @param $uid
     * @param $type
     * @param $page
     * @param $uid_arr
     * @param $find_str
     * @return array
     */
    public static function GetRewardList($uid,$type,$uid_arr,$find_str,$page){
        $data = DB::select('call pro_reward_list(?,?,?,?,?,?)',[$uid,$type,$uid_arr,$find_str,($page-1)*10,10]);
        return $data ?? null;
    }



    /**
     * 获取发布的悬赏任务
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetMyReward($uid){
        return DB::table('view_my_reward')
            ->where('uid',$uid)
            ->orderBy('id','desc')
            ->simplePaginate(10);

    }

    /**
     * 获取申请的悬赏任务
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetApplyReward($uid){
        return DB::table('view_apply_reward')
            ->where('uid',$uid)
            ->orderBy('id','desc')
            ->simplePaginate(10);
    }

    /**
     * 获取悬赏任务申请列表
     * @param $id
     * @return \Illuminate\Support\Collection
     */
    public static function GetTaskList($id){
        return DB::table('view_task_list')
            ->where('r_id',$id)
            ->where('status',0)
            ->orderBy('id','desc')
            ->get();

    }

    /**
     * 获取悬赏任务采纳列表
     * @param $id
     * @return \Illuminate\Support\Collection
     */
    public static function GetTaskAdoptList($id){
        return DB::table('view_task_list')
            ->where('r_id',$id)
            ->where('status','>',0)
            ->orderBy('id','desc')
            ->get();

    }


    /**
     * 获取指定文件列表
     * @param $ids
     * @return mixed
     */
    public static function GetFilesList($ids){
        $files = DB::table('pro_mall_task_files')
            ->whereIn('task_id',$ids)
            ->get();
        return json_decode($files,true);
    }
    /**
     * 任务申请列表添加图片地址
     * @param $items
     */
    public static function SetFileUrl(&$items){
        //获取文件地址
        $id_arr = array_map(function ($item){
                return $item['id'];
        },$items);
        $files = self::GetFilesList($id_arr);
        //添加文件访问地址
        foreach ($items as &$data){
            $id = $data['id'];
            if(!empty($id)){
                $data['files'] = array_column(array_filter($files,function ($item) use($id){
                    return $item['task_id'] == $id;
                }),'file_url');
            }
            $data['chat'] = DB::table('view_task_chat_list')
                ->where('task_id',$id)
                ->orderBy('id','desc')
                ->limit(1)->get();

        }
    }
}