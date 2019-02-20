<?php
/**
 * Created by PhpStorm.
 * User: YM
 * Date: 2019/2/18
 * Time: 10:11
 */

namespace App\Http\Controllers;

use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MsgController extends Controller
{
    use ReturnData;

    /**
     * 消息列表
     * @param Request $request
     * @return string
     */
    public function  MsgList(Request $request){
        try{
            $uid = auth()->id();
            DB::transaction(function ()use($uid) {
                //最新点赞数量
                $this->data['LikeNum'] =
                    DB::table('view_like_list')
                        ->where('issue_uid',$uid)
                        ->where('status',0)
                        ->count();
                //最新评论数量
                $this->data['CommentNum'] =
                    DB::table('view_comment_list')
                        ->where('issue_uid',$uid)
                        ->where('status',0)
                        ->count();
                //最新回复数量
                $this->data['ReplyNum'] =
                    DB::table('view_reply_list')
                        ->where('c_uid',$uid)
                        ->where('status',0)
                        ->count();
                //最新转发数量
                $this->data['TurnNum'] =
                    DB::table('view_turn_list')
                        ->where('issue_uid',$uid)
                        ->where('status',0)
                        ->count();
                //消息列表
                $msg_list = DB::table('pro_mall_message')
                    ->where('uid', $uid)
                    ->orderBy('id', 'desc')
                    ->get();
                //修改查看状态
                if(count($msg_list)>0){
                    DB::table('pro_mall_message')
                        ->where('uid',$uid)
                        ->update(['status'=>1]);
                }
                $this->data['MsgList'] = $msg_list;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 点赞列表
     * @param Request $request
     * @return string
     */
    public function  LikeList(Request $request){
        try{
            $uid = auth()->id();
            DB::transaction(function ()use($uid){
                $like_list =  DB::table('view_like_list')
                    ->where('issue_uid',$uid)
                    ->orderBy('id','desc')
                    ->get();
                //修改查看状态
                if(count($like_list)>0){
                    DB::table('pro_mall_like')
                        ->where('issue_uid',$uid)
                        ->update(['status'=>1]);
                }
                $this->data['LikeList'] = $like_list;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 评论列表
     * @param Request $request
     * @return string
     */
    public function  CommentList(Request $request){
        try{
            $uid = auth()->id();
            DB::transaction(function ()use($uid){
                $comment_list =  DB::table('view_comment_list')
                    ->where('issue_uid',$uid)
                    ->orderBy('id','desc')
                    ->get();
                //修改查看状态
                if(count($comment_list)>0){
                    DB::table('pro_mall_comment')
                        ->where('issue_uid',$uid)
                        ->where('reply_id','=',0)
                        ->update(['status'=>1]);
                }
                $this->data['CommentList'] = $comment_list;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 回复列表
     * @param Request $request
     * @return string
     */
    public function  ReplyList(Request $request){
        try{
            $uid = auth()->id();
            DB::transaction(function ()use($uid){
                $reply_list =  DB::table('view_reply_list')
                    ->where('c_uid',$uid)
                    ->orderBy('id','desc')
                    ->get();
                //修改查看状态
                if(count($reply_list)>0){
                    $ids = $reply_list->where('status',0)->pluck('id');
                    DB::table('pro_mall_comment')
                        ->whereIn('id',$ids)
                        ->update(['status'=>1]);
                }
                $this->data['ReplyList'] = $reply_list;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 转发列表
     * @param Request $request
     * @return string
     */
    public function  TurnList(Request $request){
        try{
            $uid = auth()->id();
            DB::transaction(function ()use($uid){
                $turn_list =  DB::table('view_turn_list')
                    ->where('issue_uid',$uid)
                    ->orderBy('id','desc')
                    ->get();
                //修改查看状态
                if(count($turn_list)>0){
                    DB::table('pro_mall_turn')
                        ->where('issue_uid',$uid)
                        ->update(['status'=>1]);
                }
                $this->data['TurnList'] = $turn_list;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}