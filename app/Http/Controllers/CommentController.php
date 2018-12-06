<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/25
 * Time: 11:21
 * 评论、点赞
 */

namespace App\Http\Controllers;

use App\Lib\DataComm;
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
    use ReturnData;
    /**
     * 添加点赞记录
     * @param Request $request
     * @return string
     */
    public function  Like(Request $request){
        try{
            $uid =  auth()->id();
            $pro_type = $request->input('pro_type','');
            $pro_id = $request->input('pro_id','');
            $issue_uid = $request->input('issue_uid',0);
            $source = $request->input('source',0);
            if(empty($pro_type) || empty($pro_id) || empty($issue_uid)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'pro_type或pro_id或issue_uid不能为空';
            }else{
                DB::transaction(function () use ($pro_type,$pro_id,$uid,$source,$issue_uid){
                    $like = Like::where([['release_type',$pro_type],['release_id',$pro_id],['uid',$uid]])->first();
                    if(empty($like)){ //如果该条业务，没有点过赞，则增加点赞记录
                        //保存点赞记录
                        Like::insert(['pro_type'=>$pro_type, 'pro_id'=>$pro_id, 'uid'=>$uid, 'source'=>$source, 'issue_uid'=>$issue_uid]);
                        //该条业务增加一次点赞数量
                        DataComm::Increase($pro_type,$pro_id,'likes');
                    }else{ //如果已经点赞，则表示取消点赞，则删除点赞记录
                        //删除点赞记录
                        $like->delete();
                        //该条业务减少一次点赞数量
                        DataComm::Decrement($pro_type,$pro_id,'likes');
                    }
                });
            }
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
        }
        return $this->toJson();
    }

    /**
     * 添加评论记录
     * @param Request $request
     * @return string
     */
    public function Comment(Request $request){
        try{
            $uid =  auth()->id();
            $pro_type = $request->input('pro_type','');
            $pro_id = $request->input('pro_id','');
            $comment = $request->input('comment','');
            $reply_id = $request->input('reply_id',0);
            $source = $request->input('source',0);
            $img_url = $request->input('files','');
            $issue_uid = $request->input('issue_uid',0);
            if(empty($pro_type) ||  empty($comment) || empty($pro_id) || empty($issue_uid)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'comment、pro_type、pro_id、issue_uid不能为空';
            }else{
                //生成评论记录
                $comment =  new Comment();
                $comment->pro_type = $pro_type;
                $comment->pro_id = $pro_id;
                $comment->issue_uid = $issue_uid;
                $comment->uid = $uid;
                $comment->comment = $comment;
                $comment->reply_id = $reply_id;
                $comment->source = $source;
                $comment->img_url = $img_url;
                //保存评论记录
                DB::transaction(function () use ($comment){
                    //保存评论记录
                    $comment->save();
                    //该条业务增加一次评论数量
                    DataComm::Increase($comment->pro_type,$comment->pro_id,'discuss');
                });
            }
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
        }
        return $this->toJson();
    }

    /**
     * 删除评论
     * @param Request $request
     * @return string
     */
    public function DelComment(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
            }
            DB::transaction(function ()use($id){
                $comment = Comment::find($id);
                //该条业务减少一次评论数量
                DataComm::Decrement($comment->pro_type,$comment->pro_id,'discuss');
                $comment->delete();
            });
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
        }
        return $this->toJson();
    }

}