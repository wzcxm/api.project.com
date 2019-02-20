<?php
/**
 * Created by PhpStorm.
 * User: YM
 * Date: 2019/2/19
 * Time: 17:24
 */

namespace App\Http\Controllers;

use App\Lib\ErrorCode;
use App\Models\Users;
use Illuminate\Http\Request;
use App\Lib\ReturnData;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    use ReturnData;

    /**
     * 设置登录密码
     * @param Request $request
     * @return string
     */
    public function SetLoginPwd(Request $request){
        try{
            //修改密码
            $uid = auth()->id();
            $old_pwd = $request->input('old_pwd','');
            $new_pwd = $request->input('new_pwd','');
            if(empty($new_pwd) || empty($old_pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "原密码和新密码不能为空";
                return $this->toJson();
            }
            $user =  Users::where('uid',$uid)->where('pwd',md5($old_pwd))->first();
            if(empty($user)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "原密码错误";
                return $this->toJson();
            }
            $user->pwd = md5($new_pwd);
            $user->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 设置支付密码
     * @param Request $request
     * @return string
     */
    public function SetPayPwd(Request $request){
        try{
            $uid = auth()->id();
            $pay_pwd = $request->input('pay_pwd','');
            if(empty($pay_pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "支付密码不能为空";
                return $this->toJson();
            }
            $user =  Users::find($uid);
            $user->pay_pwd = md5($pay_pwd);
            $user->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 意见反馈
     * @param Request $request
     * @return string
     */
    public function Feedback(Request $request){
        try{
            $uid = auth()->id();
            $content = $request->input('content','');
            if(empty($content) ){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "反馈内容不能为空";
                return $this->toJson();
            }
            DB::table('pro_mall_feedback')->insert(['uid'=>$uid,'content'=>$content]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}