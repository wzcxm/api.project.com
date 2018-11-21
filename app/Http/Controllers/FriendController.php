<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 16:44
 * 我的好友
 */

namespace App\Http\Controllers;


use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use App\Lib\DataComm;
use App\Models\Apply;
use App\Models\Friend;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    use ReturnData;
    /**
     * 获取用户好友
     * @param Request $request
     * @return string
     */
    public function GetFriends(Request $request){
        try{
            $uid =  auth()->id();
            $this->data['Friends'] = DataComm::GetFriends($uid);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 查找好友
     * @param Request $request
     * @return string
     */
    public function  FindFriend(Request $request){
        try{
            $number =  $request->input('value','');
            if(empty($number)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'value不能为空';
                return $this->toJson();
            }
            //根据uid/电话/邮箱/昵称查找好友
            $users = Users::where('uid',$number)
                ->orWhere('telephone',$number)
                ->orWhere('email',$number)
                ->select('uid','nickname','head_url')
                ->get();
            $this->data['Users'] = $users;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 添加好友
     * @param Request $request
     * @return string
     */
    public function AddFriend(Request $request){
        try{
            $uid = auth()->id();
            $friend_uid =  $request->input('friend_uid','');
            $remark = $request->input('remark','');
            if(empty($friend_uid)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'friend_uid不能为空';
                return $this->toJson();
            }
            //保存添加好友请求
            Apply::insert(['ask_uid'=>$uid,'reply_uid'=>$friend_uid,'remark'=>$remark]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取好友请求列表
     * @param Request $request
     * @return string
     */
    public function GetApply(Request $request){
        try{
            $uid = auth()->id();
            $this->data['Applys'] = DataComm::GetApplyList($uid);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 是否同意加为好友
     * @param Request $request
     * @return string
     */
    public function IsAgree(Request $request){
        try{
            $id = $request->input('id','');
            $type = $request->input('type','');
            if(empty($id) || empty($type)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id或status不能为空';
                return $this->toJson();
            }
            $apply = Apply::find($id);
            $apply->status = $type;
            DB::transaction(function () use($apply){
                $apply->save();
                //同意加好友，相互添加用户好友列表
                if($apply->status == DefaultEnum::YES){
                    $friends = [
                        ['uid'=>$apply->ask_uid,'friend_uid'=>$apply->reply_uid],
                        ['uid'=>$apply->reply_uid,'friend_uid'=>$apply->ask_uid]
                    ];
                    Friend::insert($friends);
                }
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取好友信息
     * @param Request $request
     * @return string
     */
    public function  GetFriendInfo(Request $request){
        try{
            $uid = $request->input('uid','');
            if(empty($uid)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'uid不能为空';
                return $this->toJson();
            }
            $user =  Users::find($uid,['uid','nickname','head_url','telephone','level','integral','email','age','address']);
            $this->data['FriendInfo'] = $user;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }

    }
}