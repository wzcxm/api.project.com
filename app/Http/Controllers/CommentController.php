<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/25
 * Time: 11:21
 * 评论、点赞
 */

namespace App\Http\Controllers;


use App\Lib\Common;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Models\Comment;
use App\Models\Files;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * 添加点赞记录
     * @param Request $request
     * @return string
     */
    public function  Like(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $release_type = $request->input('pro_type','');
            $release_id = $request->input('pro_id','');
            $issue_uid = $request->input('issue_uid',0);
            $source = $request->input('source',0);
            if(empty($release_type) || empty($release_id) || empty($issue_uid)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'pro_type、pro_id、issue_uid不能为空';
            }else{
                DB::transaction(function () use ($release_type,$release_id,$uid,$source,$issue_uid){
                    $like = Like::where([['release_type',$release_type],['release_id',$release_id],['uid',$uid]])->first();
                    if(empty($like)){ //如果该条业务，没有点过赞，则增加点赞记录
                        //保存点赞记录
                        Like::insert(
                            ['release_type'=>$release_type,
                                'release_id'=>$release_id,
                                'uid'=>$uid,
                                'source'=>$source,
                                'issue_uid'=>$issue_uid]
                        );
                        //该条业务增加一次点赞数量
                        Common::Increase($release_type,$release_id,'likenum');
                    }else{ //如果已经点赞，则表示取消点赞，则删除点赞记录
                        //删除点赞记录
                        $like->delete();
                        //该条业务减少一次点赞数量
                        Common::Decrement($release_type,$release_id,'likenum');
                    }
                });
            }
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
        }
        return $retJson->toJson();
    }

    /**
     * 添加评论记录
     * @param Request $request
     * @return string
     */
    public function Comment(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $release_type = $request->input('pro_type','');
            $release_id = $request->input('pro_id','');
            $comment = $request->input('comment','');
            $reply_id = $request->input('reply_id',0);
            $source = $request->input('source',0);
            $files = $request->input('files','');
            $issue_uid = $request->input('issue_uid',0);
            if(empty($release_type) ||  empty($comment) || empty($release_id) || empty($issue_uid)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'comment、pro_type、pro_id、issue_uid不能为空';
            }else{
                //生成评论记录
                $comment =  new Comment();
                $comment->release_type = $release_type;
                $comment->release_id = $release_id;
                $comment->issue_uid = $issue_uid;
                $comment->uid = $uid;
                $comment->comment = $comment;
                $comment->reply_id = $reply_id;
                $comment->source = $source;
                //保存评论记录
                DB::transaction(function () use ($comment,$files){
                    //保存评论记录
                    $comment->save();
                    //如评论有图片，保存图片地址
                    if(!empty($files)){
                        Files::insert(['release_type'=>ReleaseEnum::DISCUSS,'release_id'=>$comment->id,'fileurl'=>$files]);
                    }
                    //该条业务增加一次评论数量
                    Common::Increase($comment->release_type,$comment->release_id,'discussnum');
                    //如果是回复的评论，回复的评论的评论次数加1
                    if(!empty($comment->reply_id)){
                        Comment::find($comment->reply_id)->increment('discussnum');
                    }
                });
            }
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
        }
        return $retJson->toJson();
    }

    /**
     * 删除评论
     * @param Request $request
     * @return string
     */
    public function DelComment(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = 'id不能为空';
            }
            DB::transaction(function ()use($id){
                $comment = Comment::find($id);
                //该条业务减少一次评论数量
                Common::Decrement($comment->release_type,$comment->release_id,'discussnum');
                //如果是回复的评论，回复的评论的评论次数减1
                if(!empty($comment->reply_id)){
                    Comment::find($comment->reply_id)->decrement('discussnum');
                }
                $comment->delete();
            });
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
        }
        return $retJson->toJson();
    }

}