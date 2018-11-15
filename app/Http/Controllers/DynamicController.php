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
            $dynamic = Dynamic::find($id);
            if(empty($dynamic)){
                $retJson->code = ErrorCode::DATA_LOGIN;
                $retJson->message = '动态数据不存在';
                return $retJson->toJson();
            }
            $retDynamic = [
                'type'=>$dynamic->type, //发布类型：原创/转发
                'create_time'=>$dynamic->create_time, //发布时间
            ];
            self::SetRetDynamic($retDynamic,$dynamic);
            if($dynamic->type == DefaultEnum::NO){
                $retDynamic['label_name'] = $dynamic->labelInfo->name; //标签
                $retDynamic['address'] = $dynamic->address; //地址
            }else{
                $turn = $dynamic->frontDynamic;
                if(!empty($turn)){
                    $retDynamic['turn'] = [
                        'label_name' => $turn->labelInfo->name, //标签
                        'address'=>$turn->address,
                    ];
                    self::SetRetDynamic($retDynamic['turn'],$turn);
                }
            }
            //动态信息/转发动态信息
            $retJson->data['Dynamic'] = $retDynamic;
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

    //设置返回数据
    private function SetRetDynamic(&$retArr,$data){
        $retArr['id'] = $data->id;
        $retArr['uid'] = $data->uid;
        $retArr['nickname']=$data->userInfo->nickname; //发布人昵称
        $retArr['head_url']=$data->userInfo->head_url;//发布人头像
        $retArr['content'] =$data->content; //发布类容
        $retArr['turn_num'] = $data->turnnum + $data->turnnum_add; //转发次数
        $retArr['like_num'] = $data->likenum +  $data->likenum_add;//点赞次数
        $retArr['discuss_num'] = $data->discussnum + $data->discussnum_add; //评论次数
        //如有文件，加入发布文件
        if($data->isannex == DefaultEnum::YES){
            $retArr['files']= Common::GetFiles(ReleaseEnum::DYNAMIC,$data->id);
        }
    }


    /**
     * 我的普通动态列表
     * @param Request $request
     * @return string
     */
    public function GetDynamicList(Request $request){
        $retJson = new ReturnData();
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = $request->input('find_uid',auth()->id());

            //获取我的普通动态数据，每次显示10条
            $data_list = DB::table('v_dynamic_list')->where('uid',$uid)
                ->orderBy('id','desc')->simplePaginate(10);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $retJson->message = "最后一页了，没有数据了";
                return $retJson->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加文件访问地址
            Common::SetFileUrl($items,ReleaseEnum::DYNAMIC);
            $retJson->data['DynamicList'] = $items;
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
            Common::FilterRelease($items,$uid);
            //添加文件访问地址
            Common::SetFileUrl($items,ReleaseEnum::DYNAMIC);
            $retJson->data['CircleDynamic'] = $items;
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
    public function Topping(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id和type不能为空';
                return $retJson->toJson();
            }
            $table  =  Common::GetTable($type);
            if(empty($table)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'type值错误';
                return $retJson->toJson();
            }
            $model = DB::table($table)->where('id',$id)->first();
            if($model->topping == DefaultEnum::YES){
                DB::table($table)->where('id',$id)->update(['topping'=>0]);
            }else{
                DB::table($table)->where('id',$id)->update(['topping'=>1]);
            }
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
    public function DelBusiness(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id和type不能为空';
                return $retJson->toJson();
            }
            $table  =  Common::GetTable($type);
            if(empty($table)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'type值错误';
                return $retJson->toJson();
            }
            DB::table($table)->where('id',$id)->update(['isdelete'=>1]);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


}