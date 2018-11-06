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
        $ret_data = ReturnData::createReturn();
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $friends = DB::table('v_friends')
                ->where('uid',$uid)
                ->select('friend_uid','nickname','head_url')
                ->get();
            $ret_data->data['Friends'] = $friends;
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

    /**
     * 查找好友
     * @param Request $request
     * @return string
     */
    public function  FindFriend(Request $request){
        $ret_data = ReturnData::createReturn();
        try{
            $number =  $request->input('value','');
            if(empty($number)){
                $ret_data->code = ErrorCode::PARAM_ERROR;
                $ret_data->message = 'value不能为空';
                return $ret_data->toJson();
            }
            //根据uid/电话/邮箱/昵称查找好友
            $users = Users::where('uid',$number)
                ->orWhere('telephone',$number)
                ->orWhere('email',$number)
                ->select('uid','nickname','head_url')
                ->get();
            $ret_data->data['Users'] = $users;
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

    /**
     * 添加好友
     * @param Request $request
     * @return string
     */
    public function AddFriend(Request $request){
        $ret_data = ReturnData::createReturn();
        try{
            $uid = auth()->id();
            $friend_uid =  $request->input('friend_uid','');
            $remark = $request->input('remark','');
            if(empty($friend_uid)){
                $ret_data->code = ErrorCode::PARAM_ERROR;
                $ret_data->message = 'friend_uid不能为空';
                return $ret_data->toJson();
            }
            //保存添加好友请求
            Apply::insert(['ask_uid'=>$uid,'reply_uid'=>$friend_uid,'remark'=>$remark]);
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

    /**
     * 获取好友请求列表
     * @param Request $request
     * @return string
     */
    public function GetApply(Request $request){
        $ret_data = ReturnData::createReturn();
        try{
            $uid = auth()->id();
            $apply = DB::table('v_applys')
                ->where('reply_uid',$uid)
                ->select('id','ask_uid','nickname','head_url','remark','status')
                ->get();
            $ret_data->data['Applys'] = $apply;
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

    /**
     * 是否同意加为好友
     * @param Request $request
     * @return string
     */
    public function IsAgree(Request $request){
        $ret_data = ReturnData::createReturn();
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $ret_data->code = ErrorCode::PARAM_ERROR;
                $ret_data->message = 'id或status不能为空';
                return $ret_data->toJson();
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
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

    /**
     * 获取好友信息
     * @param Request $request
     * @return string
     */
    public function  GetFriendInfo(Request $request){
        $ret_data = ReturnData::createReturn();
        try{
            $uid = $request->input('uid','');
            if(empty($uid)){
                $ret_data->code = ErrorCode::PARAM_ERROR;
                $ret_data->message = 'uid不能为空';
                return $ret_data->toJson();
            }
            $user =  Users::find($uid,['uid','nickname','head_url','telephone','level','integral','email','age','address']);
            $ret_data->data['FriendInfo'] = $user;
            return $ret_data->toJson();
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }

    }
}