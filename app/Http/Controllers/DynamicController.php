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
use App\Lib\DataComm;
use App\Models\Dynamic;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicController extends Controller
{
    use ReturnData;
    /**
     * 发布动态
     * @param Request $request
     * @return string
     */
    public function EditDynamic(Request $request){
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
                    DataComm::SaveFiles(ReleaseEnum::DYNAMIC,$dynamic->id,$files);
                }
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 转发动态
     * @param Request $request
     * @return string
     */
    public function TurnDynamic(Request $request){
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '转发id不能为空';
                return $this->toJson();
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
                DataComm::Increase(ReleaseEnum::DYNAMIC,$dynamic->front_id,'turnnum');

            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取一条动态/转发动态信息
     * @param Request $request
     * @return string
     */
    public function GetDynamic(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $dynamic = DataComm::GetDynamicInfo($id);
            if(empty($dynamic)){
                $this->code = ErrorCode::DATA_LOGIN;
                $this->message = '动态数据不存在';
                return $this->toJson();
            }
            if($dynamic->type == DefaultEnum::NO){
                //如有文件，加入文件地址
                if($dynamic->isannex == DefaultEnum::YES){
                    $dynamic->files = DataComm::GetFiles(ReleaseEnum::DYNAMIC,$id);
                }
            }else{
                $turn = DataComm::GetDynamicInfo($dynamic->front_id);
                if(!empty($turn)){
                    //如有文件，加入文件地址
                    if($turn->isannex == DefaultEnum::YES){
                        $turn->files = DataComm::GetFiles(ReleaseEnum::DYNAMIC,$turn->id);
                    }
                    $dynamic->turn = $turn;
                }
            }
            //动态信息/转发动态信息
            $this->data['Dynamic'] = $dynamic;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $this->data['IsLike'] =DataComm::IsLike(ReleaseEnum::DYNAMIC,$dynamic->id,$uid);
            //评论信息
            $this->data['Comment'] = DataComm::GetComment(ReleaseEnum::DYNAMIC,$dynamic->id);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 我的普通动态列表
     * @param Request $request
     * @return string
     */
    public function GetDynamicList(Request $request){
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = $request->input('find_uid',auth()->id());

            //获取我的普通动态数据，每次显示10条
            $data_list = DataComm::GetDynamicList($uid);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加文件访问地址
            DataComm::SetFileUrl($items,ReleaseEnum::DYNAMIC);
            $this->data['DynamicList'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 圈子动态信息
     * @param Request $request
     * @return string
     */
    public function  GetCircleDynamic(Request $request){
        try{
            $uid = auth()->id();
            //获取所有还有id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list = DataComm::GetDynamicList($circle_ids);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //去除没有权限的动态
            DataComm::FilterRelease($items,$uid);
            //添加文件访问地址
            DataComm::SetFileUrl($items,ReleaseEnum::DYNAMIC);
            $this->data['CircleDynamic'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 动态置顶/取消置顶
     * @param Request $request
     * @return string
     */
    public function Topping(Request $request){
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id和type不能为空';
                return $this->toJson();
            }
            $table  =  Common::GetTable($type);
            if(empty($table)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'type值错误';
                return $this->toJson();
            }
            $model = DB::table($table)->where('id',$id)->first();
            if($model->topping == DefaultEnum::YES){
                DB::table($table)->where('id',$id)->update(['topping'=>0]);
            }else{
                DB::table($table)->where('id',$id)->update(['topping'=>1]);
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 删除动态
     * @param Request $request
     * @return string
     */
    public function DelBusiness(Request $request){
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id和type不能为空';
                return $this->toJson();
            }
            $table  =  Common::GetTable($type);
            if(empty($table)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'type值错误';
                return $this->toJson();
            }
            DB::table($table)->where('id',$id)->update(['isdelete'=>1]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


}