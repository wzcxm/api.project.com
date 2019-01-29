<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 9:21
 * 用户注册、登录、用户信息
 */

namespace App\Http\Controllers;

use App\Jobs\EmailJob;
use App\Lib\Common;
use App\Lib\DataComm;
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Users;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class UserController extends Controller
{
    use ReturnData;

    /**
     * 获取验证码
     * @param Request $request
     * @return string
     */
    public function GetCode(Request $request){
        try{
            $tel =  $request->input('tel',0);
            if(!Common::IsTelephone($tel)){
                $this->code = ErrorCode::PARAM_ERROR;;
                $this->message = "手机号格式错误";
                return $this->toJson();
            }
            //生产6位数验证码
            $code = rand(100000,999999);
            //发送验证码短信
            //Common::Send_Message($tel,$code);
            //验证码保存到缓存，2分钟有效
            $expiresAt = Carbon::now() ->addMinutes(2);
            Cache::put($tel, $code, $expiresAt);
            Log::info($code);
            $this->data = $code;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }

    /**
     * 注册校验
     * @param Request $request
     * @return string
     */
    public function RegisterCheck(Request $request){
        try{
            $tel = $request->input('tel',0);
            $code = $request->input('code',0);
            if(empty($tel)  || empty($code)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "手机号或验证码不能为空";
                return $this->toJson();
            }
            //校验验证码
//            if(!Common::CheckCode($tel,$code)){
//                $this->code = ErrorCode::PARAM_ERROR;;
//                $this->message = "验证码错误，或已失效！";
//                return $this->toJson();
//            }
            //校验是否被注册
            if(DataComm::CheckPhone($tel)){
                $this->code = ErrorCode::PARAM_ERROR;;
                $this->message = "手机号已经被注册";
                return $this->toJson();
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 用户注册
     * @param Request $request
     * @return string
     */
    public function Register(Request $request){
        try{
            $tel = $request->input('tel','');
            $pwd = $request->input('pwd','');
            $source = $request->input('source','');
            if(empty($tel) || empty($pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "手机号和密码不能为空";
                return $this->toJson();
            }
            //创建用户信息
            $user = new Users();
            $user->head_url = 'http://'.$_SERVER['HTTP_HOST'].'/head/default.png';
            $user->nickname = 'User_'.str_random(5);
            $user->telephone = $tel;
            $user->pwd = md5($pwd);
            $user->source = $source;
            $user->file_key = str_random(65);
            //事务保存用户信息，并生成用户钱包
            DB::transaction(function ()use($user){
                $user->save();
                //生成用户钱包
                $wallet = new Wallet();
                $wallet->uid = $user->uid;
                $wallet->save();
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }

    /**
     * 修改密码，检查验证码
     * @param Request $request
     * @return string
     */
    public function UpdateCheck(Request $request){
        try{
            $tel = $request->input('tel',0);
            $code = $request->input('code',0);
            if(empty($tel)  || empty($code)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "手机号或验证码不能为空";
                return $this->toJson();
            }
            //校验验证码
            if(!Common::CheckCode($tel,$code)){
                $this->code = ErrorCode::PARAM_ERROR;;
                $this->message = "验证码错误，或已失效！";
                return $this->toJson();
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 修改密码
     * @param Request $request
     * @return string
     */
    public function UpdatePwd(Request $request){
        //修改密码
        $tel = $request->input('tel','');
        $newpwd = $request->input('newpwd','');
        if(empty($newpwd) || empty($tel)){
            $this->code = ErrorCode::PARAM_ERROR;
            $this->message = "手机号或新密码不能为空";
            return $this->toJson();
        }
        $user =  Users::where('telephone',$tel)->first();
        if(empty($user)){
            $this->code = ErrorCode::PARAM_ERROR;
            $this->message = "用户不存在";
            return $this->toJson();
        }
        $user->pwd = md5($newpwd);
        $user->save();
        return $this->toJson();
    }



    /**
     * 用户登录
     * @param Request $request
     * @return string
     */
    public function Login(Request $request){
        try{
            $tel = $request->input('tel','');
            $pwd = $request->input('pwd','');
            if(empty($tel)  || empty($pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "用户名或密码不能为空";
                return $this->toJson();
            }
            $user = Users::where('telephone',$tel)->where('pwd',md5($pwd))->first();
            if(empty($user)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "手机号或密码错误";
                return $this->toJson();
            }
            //每次登录更新一次文件上传key
            $user->file_key = str_random(65);
            $user->save();
            $token = auth()->tokenById($user->uid);
            //返回用户信息
            $this->data['UserInfo'] = $user;
            $this->data['key'] = $user->file_key;
            $this->data['token'] = $token;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 退出登录
     * @param Request $request
     * @return string
     */
    public function Logout(Request $request){
        try {
            auth()->invalidate();
            return $this->toJson();
        } catch (\Exception $e) {
            $this->code = ErrorCode::TOKEN_ERROR;
            $this->message = 'token已失效';
            return $this->toJson();
        }
    }

    /**
     * 获取用户信息
     * @param Request $request
     * @return string
     */
    public function GetUserInfo(Request $request){
        try{
            $uid =  auth()->id();
            $this->data['UserInfo'] = Users::find($uid);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取用户钱包信息
     * @param Request $request
     * @return string
     */
    public function GetUserWallet(Request $request){
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $wallet = Wallet::firstOrCreate(['uid'=>$uid]);
            $this->data['Wallet'] = $wallet;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 修改用户信息
     * @param Request $request
     * @return string
     */
    public function UpdateUser(Request $request){
        try{
            $user = auth()->user();
            //修改昵称
            $nickname = $request->input('nickname','');
            if(!empty($nickname)){
                $user->nickname = $nickname;
            }
            //修改手机号
            $telephone = $request->input('telephone','');
            if(!empty($telephone)){
                if(Common::IsTelephone($telephone)){
                    if(DataComm::CheckPhone($telephone)){
                        $this->code = ErrorCode::PARAM_ERROR;
                        $this->message = "手机号已被注册";
                        return $this->toJson();
                    }else{
                        $user->telephone = $telephone;
                    }
                }else{
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = "手机号格式错误";
                    return $this->toJson();
                }
            }
            //修改Email
            $email = $request->input('email','');
            if(!empty($email) ){
                if(Common::IsEmail($email)){
                    $user->email = $email;
                } else{
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = "Email格式错误";
                    return $this->toJson();
                }
            }
            //修改年龄
            $age = $request->input('age','');
            if(!empty($age)){
                $user->age = $age;
            }
            //修改地址
            $address = $request->input('address','');
            if(!empty($address)){
                $user->address = $address;
            }
            //修改头像
            $head_url = $request->input('head_url','');
            if(!empty($head_url)){
                $user->head_url = $head_url;
            }
            $user->save();
            $this->data['UserInfo'] = $user;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


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