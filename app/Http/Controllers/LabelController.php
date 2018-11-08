<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/8
 * Time: 11:39
 */

namespace App\Http\Controllers;

use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    /**
     * 获取系统标签
     * @param Request $request
     * @return string
     */
    public function GetSysLabel(Request $request){
        $retJson = new ReturnData();
        try{
            $label = Label::where('type',1)->where('isdelete',0)->get(['id','name']);
            $retJson->data['Label'] = $label;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取用户自定义标签
     * @param Request $request
     * @return string
     */
    public function GetUserLabel(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();;
            $label = Label::where('uid',$uid)->where('isdelete',0)->get(['id','name']);
            $retJson->data['Label'] = $label;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 用户编辑标签
     * @param Request $request
     * @return string
     */
    public function EditLabel(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $id = $request->input('id','');
            $name = $request->input('name','');
            if(empty($id)){ //id不存在则表示新增
                $label = new Label();
            }else{          //id存在则修改
                $label = Label::find($id);
                $label->update_time = date("Y-m-d H:i:s");
            }
            $label->type = 2; //用户标签
            $label->name = $name;
            $label->uid = $uid;
            $label->save();
            //返回成功
            $retJson->data['Label'] = $label;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 删除用户标签
     * @param Request $request
     * @return string
     */
    public function DeleteLabel(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $id = $request->input('id','');
            Label::where([['id',$id],['uid',$uid]])->update(['isdelete'=>1]);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

}