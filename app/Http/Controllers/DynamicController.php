<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 16:42
 * 我的普通动态
 */

namespace App\Http\Controllers;

use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\AccessEnum;
use App\Lib\DataComm;
use App\Models\Dynamic;
use App\Models\Reward;
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
            $id = $request->input('id',0); //id
            $main_url = $request->input('main_url',''); //主图
            $title = $request->input('title',''); //标题
            $content = $request->input('content','');//内容
            $audio_url = $request->input('audio_url',''); //音频地址
            $is_plaza = $request->input('is_plaza',0); //是否发布到广场
            $address = $request->input('address',''); //所在地址
            $label_id = $request->input('label_id',0);//标签
            $infix_id = $request->input('infix_id',0);//商品或任务id
            $infix_type = $request->input('infix_type',0);//插入类型：0-商品;1-任务
            //生成动态model
            if(empty($id)){
                $dynamic =  new Dynamic();
                $dynamic->uid = $uid; //发布用户
            }else{
                $dynamic =  Dynamic::find($id);
                $dynamic->update_time = date("Y-m-d H:i:s");
            }
            $dynamic->main_url = $main_url; //主图
            $dynamic->title = $title; //标题
            $dynamic->content = $content; //发布内容
            $dynamic->audio_url = $audio_url; //音频地址
            $dynamic->is_plaza = $is_plaza; //是否发布到广场
            $dynamic->address = $address; //所在地址
            $dynamic->label_id = $label_id; //标签
            $dynamic->infix_id = $infix_id; //商品或任务id
            $dynamic->infix_type = $infix_type;//插入类型
            //保存动态
            $dynamic->save();
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
            $turn_id = $request->input('turn_id',0);
            $init_id = $request->input('init_id',0);
            $source = $request->input('source',0);
            if(empty($turn_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '转发id不能为空';
                return $this->toJson();
            }
            //生成转发动态model
            $dynamic =  new Dynamic();
            $dynamic->uid = $uid; //发布用户
            $dynamic->init_id = $init_id; //初始id
            $dynamic->type = DefaultEnum::YES;
            $dynamic->turn_id = $turn_id; //转发的id
            //保存动态
            DB::transaction(function () use($dynamic,$source){
                //保存转发动态
                $dynamic->save();
                $turn = Dynamic::find($dynamic->turn_id);
                //保存转发记录
                Turn::insert(
                    ['release_type'=>ReleaseEnum::DYNAMIC,
                    'release_id'=>$dynamic->turn_id,
                    'uid'=>$dynamic->uid,
                    'issue_uid'=>$turn->uid,
                    'source'=>$source]);
                //该条动态增加一次转发
                DataComm::Increase(ReleaseEnum::DYNAMIC,$dynamic->turn_id,'turns');

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
            $id = $request->input('id',0);
            $uid = auth()->id();
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
            if(!empty($dynamic->infix_id)){
                $dynamic->infix_info = DataComm::GetInfixInfo($dynamic->infix_id,$dynamic->infix_type);
            }
            //动态信息/转发动态信息
            $this->data['Dynamic'] = $dynamic;
            //当前查看用户是否点赞
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
            $uid = auth()->id();
            //获取我的普通动态数据，每次显示10条
            $data_list = DataComm::GetDynamicList($uid);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            $items = json_decode(json_encode($data_list),true);
            //添加评论
            DataComm::SetComment($items,ReleaseEnum::DYNAMIC,$uid);
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
            $items = json_decode(json_encode($data_list),true);
            //添加评论
            DataComm::SetComment($items,ReleaseEnum::DYNAMIC,$uid);
            $this->data['CircleDynamic'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 广场动态列表
     * @param Request $request
     * @return string
     */
    public function GetSquareDynamic(Request $request){
        try{
            $uid = auth()->id();
            //获取圈子普通动态数据，每次显示10条
            $data_list = DataComm::GetSquareDynamicList();
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加评论
            DataComm::SetComment($items,ReleaseEnum::DYNAMIC,$uid);
            $this->data['SquareDynamic'] = $items;
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
    public function ToppingDynamic(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $model = Dynamic::find($id);
            if(!empty($model)){
                if($model->topping == DefaultEnum::YES){
                    $model->topping = 0;
                }else{
                    $model->topping =1;
                }
                $model->save();
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
    public function DeleteDynamic(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            DB::table('pro_mall_dynamic')->where('id',$id)->update(['isdelete'=>1]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


}