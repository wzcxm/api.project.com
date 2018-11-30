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
            ->select('f.friend_uid','u.nickname','u.head_url')
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
     * @param $release_type
     * @param $release_id
     * @param $uid
     * @return bool
     */
    public static  function IsLike($release_type,$release_id,$uid){
        $count = DB::table('pro_mall_like')
            ->where('release_type',$release_type)
            ->where(  'release_id',$release_id)
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
        return DB::table('pro_mall_comment as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->where('release_type',$type)
            ->where('release_id',$id)
            ->get(['t.id','t.reply_id','t.uid','u.nickname','u.head_url','t.comment','t.likenum','t.discussnum','t.create_time']);
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
     * @param $release_type
     * @param $release_id
     * @param $column
     */
    public static function  Increase($release_type,$release_id,$column){
        $table = Common::GetTable($release_type);
        if(!empty($table)){
            //自增次数
            DB::table($table)->where('id',$release_id)->increment($column);
        }

    }

    /**
     * 自减点赞or评论or转发次数
     * @param $release_type
     * @param $release_id
     * @param $column
     */
    public static function  Decrement($release_type,$release_id,$column){
        $table = Common::GetTable($release_type);
        if(!empty($table)) {
            //自减次数
            DB::table($table)->where('id', $release_id)->decrement($column);
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
     * 获取指定文件列表
     * @param $type
     * @param $ids
     * @return mixed
     */
    public static function GetFilesList($type,$ids){
        $files = DB::table('pro_mall_files')
            ->where('release_type',$type)
            ->whereIn('release_id',$ids)
            ->get();
        return json_decode($files,true);
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
        $files = self::GetFilesList($release_type,$id_arr);
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

    /**
     * 给广场数据添加文件访问地址
     * @param $items
     * @param $release_type
     */
    public static function SetSquareFileUrl(&$items,$release_type){
        //获取文件地址
        $files_id_arr = array_map(function ($item){
            if($item['isannex'] == DefaultEnum::YES){
                return $item['id'];
            }
        },$items);
        $id_arr = array_filter(array_unique($files_id_arr));
        $files = self::GetFilesList($release_type,$id_arr);
        //添加文件访问地址
        foreach ($items as &$data){
            if($data['isannex'] == DefaultEnum::YES){
                $id =  $data['id'];
            }
            if(!empty($id)){
                $data['files'] = array_column(array_filter($files,function ($item) use($id){
                    return $item['release_id'] == $id;
                }),'fileurl');
            }
        }
    }

    /**
     * 获取一条动态详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetDynamicInfo($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.front_id,
            t.isannex,    
            t.create_time,
            t.content,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            l.name as label_name,
            t.address
EOT;

        return DB::table('pro_mall_dynamic as t')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->where('t.id',$id)
            ->selectRaw($expression)
            ->first();
    }

    /**
     * 获取动态列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetDynamicList($id){
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
            if(t.type=0,t.content,d.content) as content,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,d.address) as address,
            d.id as init_id,
            d.isannex as init_annex,
            d.uid as init_uid,
            s.nickname as init_nick,
            s.head_url as init_head
EOT;
        $data = DB::table('pro_mall_dynamic as t')
            ->leftJoin('pro_mall_dynamic as d','d.id','=','t.front_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_mall_users as s','s.uid','=','d.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','d.label')
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
     * 广场动态列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareDynamicList(){
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
            t.content,
            l.name as label_name,
            t.address
EOT;
        $data = DB::table('pro_mall_dynamic as t')
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
     * 获取一条付费商品详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetGoodsInfo($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.create_time,
            t.turnprice,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            if(t.type=0,t.title,g.title) as title,
            if(t.type=0,t.remark,g.remark) as remark,
            if(t.type=0,t.number,g.number) as number,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,g.address) as address,
            if(t.type=0,t.price,g.price) as price,
            if(t.type=0,t.firstprice,g.firstprice) as firstprice,
            if(t.type=0,t.fare,g.fare) as fare,
            if(t.type=0,t.peak,g.peak) as peak,
            if(t.type=0,if(t.isannex=1,t.id,0),if(g.isannex=1,g.id,0)) as file_id,
            (select count(0) from pro_mall_order  where order_type=0 and initial_id = if(t.type=0,t.id,g.id)) as sell_num
EOT;
        return DB::table('pro_mall_goods as t')
            ->leftJoin('pro_mall_goods as g','g.id','=','t.first_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','g.label')
            ->where('t.id',$id)
            ->selectRaw($expression)
            ->first();
    }

    /**
     * 获取付费商品列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetGoodsList($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.create_time,
            t.topping,
            t.turnprice,
            t.isannex,
            t.visible_uids,
            t.access,
            t.issquare,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            if(t.type=0,t.title,d.title) as title,
            if(t.type=0,t.remark,d.remark) as remark,
            if(t.type=0,t.number,d.number) as number,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,d.address) as address,
            if(t.type=0,t.price,d.price) as price,
            if(t.type=0,t.firstprice,d.firstprice) as firstprice,
            if(t.type=0,t.fare,d.fare) as fare,
            if(t.type=0,t.peak,d.peak) as peak,
            d.id as init_id,
            d.isannex as init_annex,
            d.isdelete as init_status
EOT;
        $data = DB::table('pro_mall_goods as t')
            ->leftJoin('pro_mall_goods as d','d.id','=','t.first_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','d.label')
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
     * 广场付费商品列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareGoodsList(){
        $expression = <<<EOT
                t.id,
                t.uid,
                u.nickname,
                u.head_url,
                t.type,
                t.create_time,
                t.topping,
                t.turnprice,
                t.isannex,
                t.turnnum+t.turnnum_add as turn_num,
                t.likenum+t.likenum_add as like_num,
                t.discussnum+t.discussnum_add as discuss_num,
                t.title,
                t.remark,
                t.number,
                l.name as label_name,
                t.address,
                t.price,
                t.firstprice,
                t.fare,
                t.peak
EOT;
        $data = DB::table('pro_mall_goods as t')
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
     * 获取一条积分商品详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function GetIntegralInfo($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.create_time,
            t.turnprice,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            if(t.type=0,t.title,g.title) as title,
            if(t.type=0,t.remark,g.remark) as remark,
            if(t.type=0,t.number,g.number) as number,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,g.address) as address,
            if(t.type=0,t.price,g.price) as price,
            if(t.type=0,t.fare,g.fare) as fare,
            if(t.type=0,t.peak,g.peak) as peak,
            if(t.type=0,if(t.isannex=1,t.id,0),if(g.isannex=1,g.id,0)) as file_id,
            (select count(0) from pro_mall_order  where order_type=0 and initial_id = if(t.type=0,t.id,g.id)) as sell_num
EOT;
        return DB::table('pro_mall_integral_goods as t')
            ->leftJoin('pro_mall_integral_goods as g','g.id','=','t.first_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','g.label')
            ->where('t.id',$id)
            ->selectRaw($expression)
            ->first();
    }

    /**
     * 获取积分商品列表
     * @param $id
     * @return \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Query\Builder
     */
    public static function GetIntegralList($id){
        $expression = <<<EOT
            t.id,
            t.uid,
            u.nickname,
            u.head_url,
            t.type,
            t.create_time,
            t.topping,
            t.turnprice,
            t.isannex,
            t.visible_uids,
            t.access,
            t.issquare,
            t.turnnum+t.turnnum_add as turn_num,
            t.likenum+t.likenum_add as like_num,
            t.discussnum+t.discussnum_add as discuss_num,
            if(t.type=0,t.title,d.title) as title,
            if(t.type=0,t.remark,d.remark) as remark,
            if(t.type=0,t.number,d.number) as number,
            if(t.type=0,l.name,a.name) as label_name,
            if(t.type=0,t.address,d.address) as address,
            if(t.type=0,t.price,d.price) as price,
            if(t.type=0,t.fare,d.fare) as fare,
            if(t.type=0,t.peak,d.peak) as peak,
            d.id as init_id,
            d.isannex as init_annex,
            d.isdelete as init_status
EOT;
        $data = DB::table('pro_mall_integral_goods as t')
            ->leftJoin('pro_mall_integral_goods as d','d.id','=','t.first_id')
            ->leftJoin('pro_mall_users as u','u.uid','=','t.uid')
            ->leftJoin('pro_sys_label as l','l.id','=','t.label')
            ->leftJoin('pro_sys_label as a','a.id','=','d.label')
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
     * 广场积分商品列表
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function GetSquareIntegralList(){
        $expression = <<<EOT
                t.id,
                t.uid,
                u.nickname,
                u.head_url,
                t.type,
                t.create_time,
                t.topping,
                t.turnprice,
                t.isannex,
                t.turnnum+t.turnnum_add as turn_num,
                t.likenum+t.likenum_add as like_num,
                t.discussnum+t.discussnum_add as discuss_num,
                t.title,
                t.remark,
                t.number,
                l.name as label_name,
                t.address,
                t.price,
                t.fare,
                t.peak
EOT;
        $data = DB::table('pro_mall_integral_goods as t')
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