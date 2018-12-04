<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/7
 * Time: 15:22
 * 我的物流地址
 */

namespace App\Http\Controllers;

use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ReturnData;
    /**
     * 编辑物流地址
     * @param Request $request
     * @return string
     */
    public function EditAddress(Request $request){
        try{
            $id =  $request->input('id','');
            $uid = auth()->id();
            $receiver = $request->input('receiver','');
            $tel = $request->input('tel','');
            $area = $request->input('area','');
            $label = $request->input('label','');
            $address = $request->input('address','');
            $default = $request->input('default',0);
            if(empty($id)){
                $address_model = new Address(); //新增
            }else{
                $address_model = Address::find($id); //修改
            }
            $address_model->uid = $uid;
            $address_model->receiver = $receiver;
            $address_model->tel = $tel;
            $address_model->address = $address;
            $address_model->default = $default;
            $address_model->area = $area;
            $address_model->label = $label;
            DB::transaction(function() use($address_model){
                //如果本条地址设为默认地址，则其他地址改为非默认地址
                if($address_model->isdefault == DefaultEnum::YES){
                    Address::where('uid',$address_model->uid)->update(['isdefault'=>0]);
                }
                $address_model->save();
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }


    /**
     * 删除物流地址
     * @param Request $request
     * @return string
     */
    public function DelAddress(Request $request){
        try{
            $id =  $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $address_model = Address::find($id); //修改
            if(empty($address_model)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '数据不存在';
                return $this->toJson();
            }
            $address_model->isdelete = 1;
            $address_model->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 获取物流地址信息
     * @param Request $request
     * @return string
     */
    public function GetAddress(Request $request){
        try{
            $id =  $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $address_model = Address::where('id',$id)->where('isdelete',0)->first();
            $this->data = $address_model;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取物流地址列表
     * @param Request $request
     * @return string
     */
    public function GetAddressList(Request $request){
        try{
            $uid = auth()->id();
            $models = Address::where('uid',$uid)->where('isdelete',0)->get();
            $this->data = $models;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}