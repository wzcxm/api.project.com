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
use App\Models\IntegralGoods;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegralControllers extends Controller
{
    /**
     * 积分商品编辑/发布
     * @param Request $request
     * @return string
     */
    public function EditIntegral(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $id = $request->input('id','');
            $name = $request->input('name','');
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
            $integral->name = $name;
            $integral->remark = $remark;
            $integral->number = $number;
            $integral->label = $label;
            $integral->address = $address;
            if($issquare == DefaultEnum::YES){
                $integral->issquare = DefaultEnum::YES;
                $integral->access = AccessEnum::PUBLIC;
            }else{
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
                    Common::SaveFiles(ReleaseEnum::INTEGRAL,$integral->id,$files);
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
     * 转卖积分商品
     * @param Request $request
     * @return string
     */
    public function TurnIntegral(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $turnprice = $request->input('turnprice',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '转卖商品id不能为空';
                return $retJson->toJson();
            }
            $integral =  new IntegralGoods();
            $integral->uid = $uid;
            $integral->buytype = DefaultEnum::YES;
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
                        'release_id'=>$integral->front_id,
                        'uid'=>$integral->uid,
                        'issue_uid'=>$issue_uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                Common::Increase(ReleaseEnum::INTEGRAL,$integral->front_id,'turnnum');
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取积分商品详情
     * @param Request $request
     * @return string
     */
    public function GetIntegral(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '商品id不能为空';
                return $retJson->toJson();
            }
            $integral = DB::table('v_integral_info') ->where('id',$id)->first();
            if(empty($integral)){
                $retJson->code = ErrorCode::DATA_LOGIN;
                $retJson->message = '数据不存在';
                return $retJson->toJson();
            }
            $ret_integral = [
                'id'=>$integral->id,
                'uid'=>$integral->uid,
                'nickname'=>$integral->nickname,
                'head_url'=>$integral->head_url,
                'create_time'=>$integral->create_time,
                'buytype'=>$integral->buytype,
                'turnprice'=>$integral->turnprice
            ];
            //原创商品
            if($integral->buytype == DefaultEnum::NO){
                Common::SetGoods($ret_integral,$integral,ReleaseEnum::INTEGRAL);
            }else{   //转卖商品
                if(!empty($integral->first_id)){
                    $turn_integral = DB::table('v_integral_info')->where('id',$integral->first_id)->first();
                    if(!empty($turn_integral)){
                        Common::SetGoods($ret_goods,$turn_integral,ReleaseEnum::INTEGRAL);
                    }
                }
            }
            //商品信息
            $retJson->data['Integral'] = $ret_integral;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $retJson->data['IsLike'] =Common::IsLike(ReleaseEnum::INTEGRAL,$integral->id,$uid);
            //评论信息
            $retJson->data['Comment'] = Common::GetComment(ReleaseEnum::INTEGRAL,$integral->id);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

}