<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/7
 * Time: 15:22
 */

namespace App\Http\Controllers;


use App\Lib\Common;
use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Address;
use function foo\func;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{

    /**
     * 编辑物流地址
     * @param Request $request
     * @return string
     */
    public function EditAddress(Request $request){
        $retJson = new ReturnData();
        try{
            $id =  $request->input('id','');
            $uid = auth()->id();
            $receiver = $request->input('receiver','');
            $telephone = $request->input('telephone','');
            $address = $request->input('address','');
            $isdefault = $request->input('isdefault',0);
            if(empty($id)){
                $address_model = new Address(); //新增
            }else{
                $address_model = Address::find($id); //修改
            }
            $address_model->uid = $uid;
            $address_model->receiver = $receiver;
            $address_model->telephone = $telephone;
            $address_model->address = $address;
            $address_model->isdefault = $isdefault;
            DB::transaction(function() use($address_model){
                //如果本条地址设为默认地址，则其他地址改为非默认地址
                if($address_model->isdefault == DefaultEnum::YES){
                    Address::where('uid',$address_model->uid)->update(['isdefault'=>0]);
                }
                $address_model->save();
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }

    }


    /**
     * 删除物流地址
     * @param Request $request
     * @return string
     */
    public function DelAddress(Request $request){
        $retJson = new ReturnData();
        try{
            $id =  $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
                return $retJson->toJson();
            }
            $address_model = Address::find($id); //修改
            if(empty($address_model)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '数据不存在';
                return $retJson->toJson();
            }
            $address_model->isdelete = 1;
            $address_model->save();
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 获取物流地址信息
     * @param Request $request
     * @return string
     */
    public function GetAddress(Request $request){
        $retJson = new ReturnData();
        try{
            $id =  $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
                return $retJson->toJson();
            }
            $address_model = Address::where('id',$id)->where('isdelete',0)->first();
            $retJson->data = $address_model;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取物流地址列表
     * @param Request $request
     * @return string
     */
    public function GetAddressList(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $models = Address::where('uid',$uid)->where('isdelete',0)->get();
            $retJson->data = $models;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }
}