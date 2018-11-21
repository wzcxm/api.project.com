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


class UserController extends Controller
{
    use ReturnData;
    /**
     * 用户注册
     * @param Request $request
     * @return string
     */
    public function Register(Request $request){
        try{
            $type = $request->input('type','');
            $username = $request->input('username','');
            $code = $request->input('code','');
            $pwd = $request->input('pwd','');

            if($type !='telephone' && $type != 'email'){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "参数type错误，必须是telephone或email";
                return $this->toJson();
            }
            if(empty($username) || empty($code) || empty($pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "用户名、密码或验证码为空";
                return $this->toJson();
            }
            //校验是否是正确的手机/Email格式
            if($type =='telephone'){
                if(!Common::IsTelephone($username)){
                    $this->code = ErrorCode::PARAM_ERROR;;
                    $this->message = "手机号格式错误";
                    return $this->toJson();
                }
            }else{
                if(!Common::IsEmail($username)){
                    $this->code = ErrorCode::PARAM_ERROR;;
                    $this->message = "Email格式错误";
                    return $this->toJson();
                }
            }
            //校验是否被注册
            if(DataComm::CheckPhoneOrEmail($type,$username)){
                $this->code = ErrorCode::PARAM_ERROR;;
                $this->message = $type=='telephone'?"手机号":"邮箱"."已经被注册";
                return $this->toJson();
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
            //$this->data['UserInfo'] = $user;
            //$this->data['key'] = $user->file_key;
            //$this->data['token'] = $token;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }

    /**
     * 用户登录
     * @param Request $request
     * @return string
     */
    public function Login(Request $request){
        try{
            $username = $request->input('username','');
            $pwd = $request->input('pwd','');
            if(empty($username)  || empty($pwd)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "用户名或密码不能为空";
                return $this->toJson();
            }
            $user = Users::where(function ($query)use($username){
                        $query->where('telephone',$username)
                              ->orWhere('email',$username);
                    })->where('pwd',md5($pwd))->first();
            if(empty($user)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = "用户名或密码错误";
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
            $user =  auth()->user();
            $this->data['UserInfo'] = $user;
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
                    if(DataComm::CheckPhoneOrEmail('telephone',$telephone)){
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
                    if(DataComm::CheckPhoneOrEmail('email',$email)){
                        $this->code = ErrorCode::PARAM_ERROR;
                        $this->message = "email已被注册";
                        return $this->toJson();
                    }else{
                        $user->email = $email;
                    }
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
            //修改密码
            $newpwd = $request->input('newpwd','');
            $oldpwd = $request->input('oldpwd','');
            if(!empty($newpwd) && !empty($oldpwd)){
                if($user->pwd == md5($oldpwd)){
                    $user->pwd = md5($newpwd);
                }else{
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = "原密码错误";
                    return $this->toJson();
                }
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
     * 获取验证码
     * @param Request $request
     * @return string
     */
    public function GetCode(Request $request){
        try{
            $type =  $request->input('type',0);
            $source = $request->input('source',0);
            if($type!= 'telephone' && $type!= 'email'){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'type错误，必须为telephone或email';
                return $this->toJson();
            }
            if($type == 'telephone') {
                if(!Common::IsTelephone($source)){
                    $this->code = ErrorCode::PARAM_ERROR;;
                    $this->message = "手机号格式错误";
                    return $this->toJson();
                }
            }else{
                if(!Common::IsEmail($source)){
                    $this->code = ErrorCode::PARAM_ERROR;;
                    $this->message = "Email格式错误";
                    return $this->toJson();
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
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }


}