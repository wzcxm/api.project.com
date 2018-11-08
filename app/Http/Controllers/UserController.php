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
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Users;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{
    /**
     * 用户注册
     * @param Request $request
     * @return string
     */
    public function Register(Request $request){
        $retJson = new ReturnData();
        try{
            $type = $request->input('type','');
            $username = $request->input('username','');
            $code = $request->input('code','');
            $pwd = $request->input('pwd','');

            if($type !='telephone' && $type != 'email'){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = "参数type错误，必须是telephone或email";
                return $retJson->toJson();
            }
            if(empty($username) || empty($code) || empty($pwd)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = "用户名、密码或验证码为空";
                return $retJson->toJson();
            }
            //校验是否是正确的手机/Email格式
            if($type =='telephone'){
                if(!Common::IsTelephone($username)){
                    $retJson->code = ErrorCode::PARAM_ERROR;;
                    $retJson->message = "手机号格式错误";
                    return $retJson->toJson();
                }
            }else{
                if(!Common::IsEmail($username)){
                    $retJson->code = ErrorCode::PARAM_ERROR;;
                    $retJson->message = "Email格式错误";
                    return $retJson->toJson();
                }
            }
            //校验是否被注册
            if(Common::CheckPhoneOrEmail($type,$username)){
                $retJson->code = ErrorCode::PARAM_ERROR;;
                $retJson->message = $type=='telephone'?"手机号":"邮箱"."已经被注册";
                return $retJson->toJson();
            }
            //校验验证码是否合法或过期
//            if(!Common::CheckCode($username,$code)){
//                $ret_data->code = ErrorCode::PARAM_ERROR;;
//                $ret_data->message = "验证码错误，或已失效！";
//                return $ret_data->toJson();
//            }
            //创建用户信息
            $user = new Users();
            if($type == 'telephone')
                $user->telephone = $username;
            else
                $user->email = $username;
            $user->pwd = md5($pwd);
            $user->file_key = str_random(65);
            //事务保存用户信息，并生成用户钱包
            DB::transaction(function ()use($user){
                $user->save();
                //生成用户钱包
                $wallet = new Wallet();
                $wallet->uid = $user->uid;
                $wallet->save();
            });
            //生成token
            //$token = auth()->tokenById($user->uid);
            //返回客户端，用户信息
            //$retJson->data['UserInfo'] = $user;
            //$retJson->data['key'] = $user->file_key;
            //$retJson->data['token'] = $token;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }

    }

    /**
     * 用户登录
     * @param Request $request
     * @return string
     */
    public function Login(Request $request){
        $retJson = new ReturnData();
        try{
            $username = $request->input('username','');
            $pwd = $request->input('pwd','');
            if(empty($username)  || empty($pwd)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = "用户名或密码不能为空";
                return $retJson->toJson();
            }
            $user = Users::where(function ($query)use($username){
                        $query->where('telephone',$username)
                              ->orWhere('email',$username);
                    })->where('pwd',md5($pwd))->first();
            if(empty($user)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = "用户名或密码错误";
                return $retJson->toJson();
            }
            //每次登录更新一次文件上传key
            $user->file_key = str_random(65);
            $user->save();
            $token = auth()->tokenById($user->uid);
            //返回用户信息
            $retJson->data['UserInfo'] = $user;
            $retJson->data['key'] = $user->file_key;
            $retJson->data['token'] = $token;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 退出登录
     * @param Request $request
     * @return string
     */
    public function Logout(Request $request){
        $retJson = new ReturnData();
        try {
            auth()->invalidate();
            return $retJson->toJson();
        } catch (\Exception $e) {
            $retJson->code = ErrorCode::TOKEN_ERROR;
            $retJson->message = 'token已失效';
            return $retJson->toJson();
        }
    }

    /**
     * 获取用户信息
     * @param Request $request
     * @return string
     */
    public function GetUserInfo(Request $request){
        $retJson = new ReturnData();
        try{
            $user =  auth()->user();
            $retJson->data['UserInfo'] = $user;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取用户钱包信息
     * @param Request $request
     * @return string
     */
    public function GetUserWallet(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $wallet = Wallet::firstOrCreate(['uid'=>$uid]);
            $retJson->data['Wallet'] = $wallet;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 修改用户信息
     * @param Request $request
     * @return string
     */
    public function UpdateUser(Request $request){
        $retJson = new ReturnData();
        try{
            //$uid =  $request->input('uid','');
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
                    if(Common::CheckPhoneOrEmail('telephone',$telephone)){
                        $retJson->code = ErrorCode::PARAM_ERROR;
                        $retJson->message = "手机号已被注册";
                        return $retJson->toJson();
                    }else{
                        $user->telephone = $telephone;
                    }
                }else{
                    $retJson->code = ErrorCode::PARAM_ERROR;
                    $retJson->message = "手机号格式错误";
                    return $retJson->toJson();
                }
            }
            //修改Email
            $email = $request->input('email','');
            if(!empty($email) ){
                if(Common::IsEmail($email)){
                    if(Common::CheckPhoneOrEmail('email',$email)){
                        $retJson->code = ErrorCode::PARAM_ERROR;
                        $retJson->message = "email已被注册";
                        return $retJson->toJson();
                    }else{
                        $user->telephone = $email;
                    }
                } else{
                    $retJson->code = ErrorCode::PARAM_ERROR;
                    $retJson->message = "Email格式错误";
                    return $retJson->toJson();
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
            //修改密码
            $newpwd = $request->input('newpwd','');
            $oldpwd = $request->input('oldpwd','');
            if(!empty($newpwd) && !empty($oldpwd)){
                if($user->pwd == md5($oldpwd)){
                    $user->pwd = md5($newpwd);
                }else{
                    $retJson->code = ErrorCode::PARAM_ERROR;
                    $retJson->message = "原密码错误";
                    return $retJson->toJson();
                }
            }
            //修改头像
            $head_url = $request->input('head_url','');
            if(!empty($head_url)){
                $user->head_url = $head_url;
            }
            $user->save();
            $retJson->data['UserInfo'] = $user;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }





    /**
     * 获取验证码
     * @param Request $request
     * @return string
     */
    public function GetCode(Request $request){
        $retJson =  new ReturnData();
        try{
            $type =  $request->input('type',0);
            $source = $request->input('source',0);
            if($type!= 'telephone' && $type!= 'email'){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'type错误，必须为telephone或email';
                return $retJson->toJson();
            }
            if($type == 'telephone') {
                if(!Common::IsTelephone($source)){
                    $retJson->code = ErrorCode::PARAM_ERROR;;
                    $retJson->message = "手机号格式错误";
                    return $retJson->toJson();
                }
            }else{
                if(!Common::IsEmail($source)){
                    $retJson->code = ErrorCode::PARAM_ERROR;;
                    $retJson->message = "Email格式错误";
                    return $retJson->toJson();
                }
            }
            //生产6位数验证码
            $code = rand(100000,999999);
            if($type == 'email'){
                //放入队列，发送邮件
                dispatch(new EmailJob($code,$source));
            }else{
                //发送短信

            }
            //验证码保存到缓存，2分钟有效
            $expiresAt = Carbon::now() ->addMinutes(2);
            Cache::put($source, $code, $expiresAt);

            //Log::info($code);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }

    }


}