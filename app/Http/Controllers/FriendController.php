<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 16:44
 */

namespace App\Http\Controllers;


use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Models\Apply;
use App\Models\Friend;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    /**
     * 获取用户好友
     * @param Request $request
     * @return string
     */
    public function GetFriends(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $friends = DB::table('v_friends')
                ->where('uid',$uid)
                ->select('friend_uid','nickname','head_url')
                ->get();
            $retJson->data['Friends'] = $friends;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 查找好友
     * @param Request $request
     * @return string
     */
    public function  FindFriend(Request $request){
        $retJson = new ReturnData();
        try{
            $number =  $request->input('value','');
            if(empty($number)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'value不能为空';
                return $retJson->toJson();
            }
            //根据uid/电话/邮箱/昵称查找好友
            $users = Users::where('uid',$number)
                ->orWhere('telephone',$number)
                ->orWhere('email',$number)
                ->select('uid','nickname','head_url')
                ->get();
            $retJson->data['Users'] = $users;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 添加好友
     * @param Request $request
     * @return string
     */
    public function AddFriend(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $friend_uid =  $request->input('friend_uid','');
            $remark = $request->input('remark','');
            if(empty($friend_uid)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'friend_uid不能为空';
                return $retJson->toJson();
            }
            //保存添加好友请求
            Apply::insert(['ask_uid'=>$uid,'reply_uid'=>$friend_uid,'remark'=>$remark]);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 获取好友请求列表
     * @param Request $request
     * @return string
     */
    public function GetApply(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $apply = DB::table('v_applys')
                ->where('reply_uid',$uid)
                ->select('id','ask_uid','nickname','head_url','remark','status')
                ->get();
            $retJson->data['Applys'] = $apply;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    /**
     * 是否同意加为好友
     * @param Request $request
     * @return string
     */
    public function IsAgree(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id或status不能为空';
                return $retJson->toJson();
            }
            $apply = Apply::find($id);
            $apply->status = $type;
            DB::transaction(function () use($apply){
                $apply->save();
                //同意加好友，相互添加用户好友列表
                if($apply->status == 1){
                    $friends = [
                        ['uid'=>$apply->ask_uid,'friend_uid'=>$apply->reply_uid],
                        ['uid'=>$apply->reply_uid,'friend_uid'=>$apply->ask_uid]
                    ];
                    Friend::insert($friends);
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
     * 获取好友信息
     * @param Request $request
     * @return string
     */
    public function  GetFriendInfo(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = $request->input('uid','');
            if(empty($uid)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'uid不能为空';
                return $retJson->toJson();
            }
            $user =  Users::find($uid,['uid','nickname','head_url','telephone','level','integral','email','age','address']);
            $retJson->data['FriendInfo'] = $user;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }

    }
}