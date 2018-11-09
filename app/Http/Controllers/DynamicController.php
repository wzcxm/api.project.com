<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 16:42
 * 我的普通动态
 */

namespace App\Http\Controllers;

use App\Lib\Common;
use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\AccessEnum;
use App\Models\Dynamic;
use App\Models\Files;
use App\Models\Like;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicController extends Controller
{

    /**
     * 发布动态
     * @param Request $request
     * @return string
     */
    public function EditDynamic(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $id = $request->input('id','');
            $content = $request->input('content','');
            $access = $request->input('access',0);
            $issquare = $request->input('issquare',0);
            $label = $request->input('label',0);
            $visible_uids = $request->input('visible_uids','');
            $files = $request->input('files','');
            $address = $request->input('address','');
            //生成动态model
            if(empty($id)){
                $dynamic =  new Dynamic();
                $dynamic->uid = $uid; //发布用户
            }else{
                $dynamic =  Dynamic::find($id);
                $dynamic->update_time = date("Y-m-d H:i:s");
            }
            $dynamic->content = $content; //发布内容
            if($issquare == DefaultEnum::YES){    //如果允许发布到广场，那么访问权限默认是公开的
                $dynamic->issquare = DefaultEnum::YES;
                $dynamic->access = AccessEnum::PUBLIC;
            }else{
                $dynamic->access = $access;
                if($access == AccessEnum::PARTIAL){          //如果是部分用户可见，则保存可见用户（数组形式）
                    $dynamic->visible_uids = explode('|',$visible_uids);
                }
            }
            $dynamic->label = $label;  //标签
            $dynamic->address = $address;   //所在地址
            if(!empty($files)){    //如果文件不为空，那么表示有附件
                $dynamic->isannex = DefaultEnum::YES;
            }
            //保存动态
            DB::transaction(function () use($dynamic,$files){
                //保存动态
                $dynamic->save();
                //如有文件，保存文件记录
                if(!empty($files)){
                    //保存文件
                    Common::SaveFiles(ReleaseEnum::DYNAMIC,$dynamic->id,$files);
                }
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 转发动态
     * @param Request $request
     * @return string
     */
    public function TurnDynamic(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '转发id不能为空';
                return $retJson->toJson();
            }
            //生成转发动态model
            $dynamic =  new Dynamic();
            $dynamic->uid = $uid; //发布用户
            $dynamic->content = '转发动态'; //发布内容
            $dynamic->type = DefaultEnum::YES;
            $dynamic->front_id = $front_id; //转发的id
            //保存动态
            DB::transaction(function () use($dynamic,$source){
                //保存转发动态
                $dynamic->save();
                $turn = Dynamic::find($dynamic->front_id);
                //保存转发记录
                Turn::insert(
                    ['release_type'=>ReleaseEnum::DYNAMIC,
                    'release_id'=>$dynamic->front_id,
                    'uid'=>$dynamic->uid,
                    'issue_uid'=>$turn->uid,
                    'source'=>$source]);
                //该条动态增加一次转发
                Common::Increase(ReleaseEnum::DYNAMIC,$dynamic->front_id,'turnnum');

            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取一条动态/转发动态信息
     * @param Request $request
     * @return string
     */
    public function GetDynamic(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
                return $retJson->toJson();
            }
            $dynamic = DB::table('v_dynamic')->where('id',$id)->first();
            if(empty($dynamic)){
                $retJson->code = ErrorCode::DATA_LOGIN;
                $retJson->message = '动态数据不存在';
                return $retJson->toJson();
            }
            if($dynamic->type == DefaultEnum::YES) { //转发，加入转发的动态信息
                if(!empty($dynamic->front_id)){
                    //获取被转发的动态信息
                    $turn_dynamic = DB::table('v_dynamic')
                        ->where('id',$dynamic->front_id)
                        ->select(['id','uid','nickname','head_url','content',
                            'like_num','discuss_num','turn_num','label_name',
                            'address','isannex'])
                        ->first();
                    if(empty($turn_dynamic)){
                        //获取被转动态的文件地址
                        if($turn_dynamic->isannex == DefaultEnum::YES){
                            $turn_dynamic->files=Common::GetFiles(ReleaseEnum::DYNAMIC,$turn_dynamic->id);
                        }
                        $dynamic->turn =  $turn_dynamic;
                    }
                }
            }else{//原创动态，如果有文件，加入文件地址
                //如有文件，加入发布文件
                if($dynamic->isannex == DefaultEnum::YES){
                    $dynamic->files= Common::GetFiles(ReleaseEnum::DYNAMIC,$dynamic->id);
                }
            }
            //动态信息/转发动态信息
            $retJson->data['Dynamic'] = $dynamic;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $retJson->data['IsLike'] =Common::IsLike(ReleaseEnum::DYNAMIC,$dynamic->id,$uid);
            //评论信息
            $retJson->data['Comment'] = Common::GetComment(ReleaseEnum::DYNAMIC,$dynamic->id);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 我的普通动态列表
     * @param Request $request
     * @return string
     */
    public function MyDynamic(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $find_uid = $request->input('find_uid','');
            //$find_uid不为空时，表示查询该用户的动态列表
            if(!empty($find_uid)){
                $uid = $find_uid;
            }
            //获取我的普通动态数据，每次显示10条
            $data_list = DB::table('v_dynamic_list')->where('uid',$uid)
                ->orderBy('id','desc')->simplePaginate(10);

            if(count($data_list)<=0){
                $retJson->message = "最后一页了，没有数据了";
                return $retJson->toJson();
            }
            //获取文件地址
            $data_list = $data_list->items();
            $items = json_decode(json_encode($data_list),true);
            //获取动态id
            $files_id_arr = array_map(function ($item){
                if($item['type'] == DefaultEnum::NO && $item['isannex'] == DefaultEnum::YES){
                    return $item['id'];
                }else if($item['type'] == DefaultEnum::YES && $item['isannex_z'] == DefaultEnum::YES){
                    return $item['front_id'];
                }
            },$items);
            //去除null和重复的值
            $files_id_arr = array_filter(array_unique($files_id_arr));
            //获取所有的文件地址
            $files = Files::where('release_type',ReleaseEnum::DYNAMIC)
                ->whereIn('release_id',$files_id_arr)
                ->get(['release_id','fileurl']);
            $files = json_decode($files,true);
            foreach ($data_list as $data){
                //添加文件
                if($data->type == DefaultEnum::NO && $data->isannex == DefaultEnum::YES){
                    $id =  $data->id;
                }else if($data->type == DefaultEnum::YES && $data->isannex_z == DefaultEnum::YES){
                    $id =  $data->front_id;
                }
                if(!empty($id)){
                    $data->files = array_column(array_filter($files,function ($itme) use($id){
                        return $itme['release_id'] == $id;
                    }),'fileurl');
                }
            }
            $retJson->data['MyDynamic'] = $data_list;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 圈子动态信息
     * @param Request $request
     * @return string
     */
    public function  GetCircleDynamic(Request $request){
        $retJson =  new ReturnData();
        try{
            $uid = auth()->id();
            //获取所有还有id和自己的id
            $circle_ids = Common::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list = DB::table('v_dynamic_list')->whereIn('uid',$circle_ids)
                ->orderBy('id','desc')->simplePaginate(10);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $retJson->message = "最后一页，没有数据了";
                return $retJson->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
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
                            if(in_array($item['visible_uids'],$uid) || $item['uid'] == $uid){
                                return $item;
                            }
                        }
                    }else{
                        return $item;
                    }
            });
            //获取动态id
            $files_id_arr = array_map(function ($item){
                if($item['type'] == DefaultEnum::NO && $item['isannex'] == DefaultEnum::YES){
                    return $item['id'];
                }else if($item['type'] == DefaultEnum::YES && $item['isannex_z'] == DefaultEnum::YES){
                    return $item['front_id'];
                }
            },$items);
            //去除null和重复的值
            $files_id_arr = array_filter(array_unique($files_id_arr));
            //获取所有的文件地址
            $files = Files::where('release_type',ReleaseEnum::DYNAMIC)
                ->whereIn('release_id',$files_id_arr)
                ->get(['release_id','fileurl']);
            $files = json_decode($files,true);
            foreach ($data_list as $data){
                //添加文件
                if($data->type == DefaultEnum::NO && $data->isannex == DefaultEnum::YES){
                    $id =  $data->id;
                }else if($data->type == DefaultEnum::YES && $data->isannex_z == DefaultEnum::YES){
                    $id =  $data->front_id;
                }
                if(!empty($id)){
                    $data->files = array_column(array_filter($files,function ($item) use($id){
                        return $item['release_id'] == $id;
                    }),'fileurl');
                }
            }
            $retJson->data['MyDynamic'] = $data_list;
            return $retJson->toJson();

        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 动态置顶/取消置顶
     * @param Request $request
     * @return string
     */
    public function DynamicTopping(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
                return $retJson->toJson();
            }
            $dynamic =Dynamic::find($id);
            $dynamic->topping = $dynamic->topping == DefaultEnum::YES ? DefaultEnum::NO : DefaultEnum::YES;
            $dynamic->save();
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 删除动态
     * @param Request $request
     * @return string
     */
    public function DelDynamic(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
                return $retJson->toJson();
            }
            DB::transaction(function ()use($id){
                $dynamic =Dynamic::find($id);
                $dynamic->isdelete = 1;
                $dynamic->save();
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


}