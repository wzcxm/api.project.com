<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/12
 * Time: 9:32
 */

namespace App\Http\Controllers;

use App\Lib\AccessEnum;
use App\Lib\Common;
use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\DataComm;
use App\Models\IntegralGoods;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegralControllers extends Controller
{
    use ReturnData;
    /**
     * 积分商品编辑/发布
     * @param Request $request
     * @return string
     */
    public function EditIntegral(Request $request){
        try{
            $uid = auth()->id();
            $id = $request->input('id','');
            $title = $request->input('title','');
            $remark = $request->input('remark','');
            $number = $request->input('number',0);
            $label = $request->input('label',0);
            $address = $request->input('address','');
            $access = $request->input('access',0);
            $issquare = $request->input('issquare',0);
            $visible_uids = $request->input('visible_uids','');
            $price = $request->input('price',0);
            $fare = $request->input('fare',0);
            $limit = $request->input('limit',0);
            $files = $request->input('files','');
            if(empty($id)){
                $integral = new IntegralGoods();
                $integral->uid = $uid;
                $integral->price = $price;
                $integral->fare = $fare;
                $integral->limit = $limit;
            }else{
                $integral = IntegralGoods::find($id);
                $integral->update_time = date("Y-m-d H:i:s");
            }
            $integral->title = $title;
            $integral->remark = $remark;
            $integral->number = $number;
            $integral->label = $label;
            $integral->address = $address;
            if($issquare == DefaultEnum::YES){
                $integral->issquare = DefaultEnum::YES;
                $integral->access = AccessEnum::PUBLIC;
            }else{
                $integral->issquare = DefaultEnum::NO;
                $integral->access = $access;
                if($access == AccessEnum::PARTIAL){          //如果是部分用户可见，则保存可见用户（数组形式）
                    $integral->visible_uids = explode('|',$visible_uids);
                }
            }
            if(!empty($files)){
                $integral->isannex = DefaultEnum::YES;
            }
            DB::transaction(function() use($integral,$files){
                $integral->save();
                if(!empty($files)){
                    //保存文件地址
                    DataComm::SaveFiles(ReleaseEnum::INTEGRAL,$integral->id,$files);
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
     * 转卖积分商品
     * @param Request $request
     * @return string
     */
    public function TurnIntegral(Request $request){
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $turnprice = $request->input('turnprice',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '转卖商品id不能为空';
                return $this->toJson();
            }
            $integral =  new IntegralGoods();
            $integral->uid = $uid;
            $integral->type = DefaultEnum::YES;
            $integral->front_id = $front_id;
            $integral->turnprice = $turnprice;
            $turn_integral = IntegralGoods::find($front_id);
            if($turn_integral->buytype == DefaultEnum::NO){
                $integral->first_id = $front_id;
            }else{
                $integral->first_id = $turn_integral->first_id;
            }
            $issue_uid = $turn_integral->uid;
            DB::transaction(function() use($integral,$source,$issue_uid){
                $integral->save();
                //保存转卖记录
                Turn::insert(
                    ['release_type'=>ReleaseEnum::GOODS,
                        'release_id'=>$integral->first_id,
                        'uid'=>$integral->uid,
                        'issue_uid'=>$issue_uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                DataComm::Increase(ReleaseEnum::INTEGRAL,$integral->first_id,'turnnum');
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取积分商品详情
     * @param Request $request
     * @return string
     */
    public function GetIntegral(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '商品id不能为空';
                return $this->toJson();
            }
            $integral = DataComm::GetIntegralInfo($id);
            if(empty($integral)){
                $this->code = ErrorCode::DATA_LOGIN;
                $this->message = '数据不存在';
                return $this->toJson();
            }
            //添加文件地址
            if(!empty($integral->file_id)){
                $integral->files = DataComm::GetFiles(ReleaseEnum::INTEGRAL,$integral->file_id);
            }
            //商品信息
            $this->data['Integral'] = $integral;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $this->data['IsLike'] =DataComm::IsLike(ReleaseEnum::INTEGRAL,$integral->id,$uid);
            //评论信息
            $this->data['Comment'] = DataComm::GetComment(ReleaseEnum::INTEGRAL,$integral->id);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 获取积分商品列表
     * @param Request $request
     * @return string
     */
    public function GetIntegralList(Request $request){
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = $request->input('find_uid',auth()->id());

            //获取我的普通动态数据，每次显示10条
            $data_list = DataComm::GetIntegralList($uid);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加文件地址
            DataComm::SetFileUrl($items,ReleaseEnum::INTEGRAL);
            $this->data['IntegralList'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 获取朋友圈积分商品列表
     * @param Request $request
     * @return string
     */
    public function GetCircleIntegral(Request $request){
        try{
            $uid = auth()->id();
            //获取所有好友id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list = DataComm::GetIntegralList($circle_ids);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //去除没有权限的商品
            DataComm::FilterRelease($items,$uid);
            //添加文件访问地址
            DataComm::SetFileUrl($items,ReleaseEnum::INTEGRAL);
            $this->data['CircleIntegral'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取我的商品-我发布的
     * @param Request $request
     * @return string
     */
    public function GetMyPostedList(Request $request){
        try{
            $uid = auth()->id();
            $data = DataComm::GetPostedList($uid);
            $this->data['Posted'] = $data->items();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取我的商品-我代理的
     * @param Request $request
     * @return string
     */
    public function GetMyProxyList(Request $request){
        try{
            $uid = auth()->id();
            $data = DataComm::GetProxyList($uid);
            $this->data['Proxy'] = $data->items();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}