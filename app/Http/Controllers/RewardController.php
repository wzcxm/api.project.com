<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/19
 * Time: 14:16
 */

namespace App\Http\Controllers;

use App\Lib\Common;
use App\Lib\DataComm;
use App\Lib\DefaultEnum;
use App\Lib\FundsEnum;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\ErrorCode;
use App\Lib\TaskStatus;
use App\Models\Reward;
use App\Models\Task;
use App\Models\TaskChat;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    use ReturnData;

    /**
     * 发布/修改悬赏任务
     * @param Request $request
     * @return string
     */
    public function EditReward(Request $request){
        try{
            $uid = auth()->id();
            $id = $request->input('id','');
            $main_url = $request->input('main_url',''); //主图
            $title = $request->input('title',''); //标题
            $content = $request->input('content','');//内容
            $is_plaza = $request->input('is_plaza',0); //是否发布到广场
            $address = $request->input('address',''); //所在地址
            $label_id = $request->input('label_id',0);//标签
            $amount = $request->input('amount',0); //任务数量
            $price = $request->input('price',0); //单价
            $hope_time = $request->input('hope_time','');  //期望完成时间
            $pay_no = $request->input('pay_no',''); //支付号
            if(empty($id)){
                $reward = new Reward();
                $reward->uid = $uid;
                $reward->price = $price;

            }else{
                $reward = Reward::find($id);
            }
            $reward->title = $title;
            $reward->main_url = $main_url;
            $reward->content = $content;
            $reward->label_id = $label_id;
            $reward->address = $address;
            $reward->is_plaza = $is_plaza;
            $reward->amount = $amount;
            $reward->bounty = $reward->price*$amount;
            $reward->hope_time = $hope_time;
            $reward->pay_no = $pay_no;
            $reward->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取一条悬赏任务详情
     * @param Request $request
     * @return string
     */
    public function GetReward(Request $request){
        try{
            $id = $request->input('id',0);
            $uid = auth()->id();
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $reward = DB::select('call pro_get_reward(?,?)',[$id,$uid]);
            if(empty($reward)){
                $this->code = ErrorCode::DATA_LOGIN;
                $this->message = '数据不存在';
                return $this->toJson();
            }
            //任务信息
            $this->data['Reward'] = $reward;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 悬赏任务列表
     * @param Request $request
     * @return string
     */
    public function GetRewardList(Request $request){
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = auth()->id();
            $page = $request->input('page',1);
            //获取我的任务数据，每次显示10条
            $data_list = DataComm::GetRewardList($uid,1,'','',$page);
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            $this->data['RewardList'] = $data_list;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 圈子悬赏任务列表
     * @param Request $request
     * @return string
     */
    public function GetCircleReward(Request $request){
        try{
            $uid = auth()->id();
            $page = $request->input('page',1);
            $keyword = $request->input('keyword','');
            //获取所有还有id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            $uid_arr = implode(",", $circle_ids);
            //获取圈子普通动态数据，每次显示10条
            $data_list =DataComm::GetRewardList($uid,3,$uid_arr,$keyword,$page);
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            $this->data['CircleReward'] = $data_list;
            return $this->toJson();

        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 广场悬赏任务列表
     * @param Request $request
     * @return string
     */
    public function GetSquareReward(Request $request){
        try{
            $uid = auth()->id();
            $page = $request->input('page',1);
            $keyword = $request->input('keyword','');
            //获取广场悬赏任务列表，每次显示10条
            $data_list = DataComm::GetRewardList($uid,2,'',$keyword,$page);
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            $this->data['SquareReward'] = $data_list;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 任务置顶/取消置顶
     * @param Request $request
     * @return string
     */
    public function ToppingReward(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $model = Reward::find($id);
            if(!empty($model)){
                if($model->topping == DefaultEnum::YES){
                    $model->topping = 0;
                }else{
                    $model->topping =1;
                }
                $model->save();
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 删除任务
     * @param Request $request
     * @return string
     */
    public function DeleteReward(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $count = DB::table('pro_mall_task')
                ->where('r_id',$id)
                ->where('status','>',0)
                ->where('status','<',3)
                ->count();
            if($count>0){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '该任务还未完成，不能删除！';
                return $this->toJson();
            }

            DB::table('pro_mall_reward')->where('id',$id)->update(['isdelete'=>1]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 悬赏任务申请
     * @param Request $request
     * @return string
     */
    public function ApplyReward(Request $request){
        try{
            $uid = auth()->id();
            $r_id = $request->input('r_id',0);
            $price = $request->input('price',0);
            $apply = $request->input('apply','');
            $files = $request->input('files','');
            if(empty($r_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $count =  Task::where('r_id',$r_id)->where('uid',$uid)->count();
            if($count>0){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '您已经申请过该任务，不能重复申请！';
                return $this->toJson();
            }
            $task = new Task();
            $task->r_id = $r_id;
            $task->apply = $apply;
            $task->uid = $uid;
            $task->price = $price;
            if(!empty($files)){
                $task->is_annex = DefaultEnum::YES;
            }
            DB::transaction(function ()use($task,$files){
                $task->save();
                //保存申请图片
                if(!empty($files)){
                    $file_urls = explode('|',$files);
                    $file_arr = array();
                    foreach ($file_urls as $url){
                        $file_arr[] = ['task_id'=>$task->id,'file_url'=>$url];
                    }
                    //先清空
                    DB::table('pro_mall_task_files')->where('task_id',$task->id)->delete();
                    //保存文件地址
                    DB::table('pro_mall_task_files')->insert($file_arr);
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
     * 获取悬赏任务的申请列表
     * @param Request $request
     * @return string
     */
    public function GetTaskList(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $task_list = DataComm::GetTaskList($id);
            $items = json_decode(json_encode($task_list),true);
            DataComm::SetFileUrl($items);
            $this->data['TaskList'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 设置订单状态
     * @param Request $request
     * @return string
     */
    public function SetTask(Request $request){
        try{
            $id = $request->input('id',0);
            $status = $request->input('status',0);
            if(empty($id) || empty($status)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id或status不能为空';
                return $this->toJson();
            }
            DB::transaction(function ()use($status,$id){
                switch ($status){
                    case TaskStatus::ACCEPT:   //采纳
                        $task = Task::find($id);
                        $reward = Reward::find($task->r_id);
                        if($reward->amount<=0){
                            $this->code = ErrorCode::PARAM_ERROR;
                            $this->message = '申请名额已用尽，不能采纳了';
                            return $this->toJson();
                        }
                        $task->status = TaskStatus::ACCEPT;
                        $task->being_time = date("Y-m-d H:i:s");
                        $task->save();
                        $reward->amount -= 1;  //任务数量减一
                        if(empty($reward->being_time)) {  //任务的执行时间为空时，改当前时间为执行时间
                            $reward->being_time = date("Y-m-d H:i:s");
                        }
                        $reward->status = 1;
                        $reward->save();
                        break;
                    case TaskStatus::COMPLY:   //提交
                        $task = Task::find($id);
                        $task->status = TaskStatus::COMPLY;
                        $task->submit_time = date("Y-m-d H:i:s");
                        $task->save();
                        break;
                    case TaskStatus::COMPLETED:  //完成
                        $task = Task::find($id);
                        $task->status = TaskStatus::COMPLETED;
                        $task->end_time = date("Y-m-d H:i:s");
                        $task->save();
                        //给猎手转钱，并保存资金流水
                        $wallet = Wallet::firstOrCreate(['uid'=>$task->uid]);
                        $wallet->amount += $task->price;
                        $wallet->save();
                        DB::table('pro_mall_funds')->insert([
                            'uid'=>$task->uid,
                            'type'=>FundsEnum::FINISH,
                            'amount'=>$task->price,
                            'balance'=>$wallet->amount,
                            'pro_id'=>$task->r_id,
                            'pro_name'=>'完成任务佣金',
                            'in_out'=>1
                        ]);
                        //所有订单完成时，悬赏任务改为完成状态
                        if(Common::Is_Completed($task->r_id)){
                            $reward = Reward::find($task->r_id);
                            $reward->status = 2;
                            $reward->end_time = date("Y-m-d H:i:s");
                            $reward->save();
                        }
                        break;
                    default:
                        break;
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
     * 任务沟通
     * @param Request $request
     * @return string
     */
    public function TaskChat(Request $request){
        try{
            $uid = auth()->id();
            $t_id = $request->input('t_id',0);
            $content = $request->input('content','');
            $img_url = $request->input('img_url','');
            if(empty($t_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 't_id不能为空';
                return $this->toJson();
            }
            $task_chat = new TaskChat();
            $task_chat->task_id = $t_id;
            $task_chat->content = $content;
            $task_chat->img_url = $img_url;
            $task_chat->uid = $uid;
            $task_chat->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取悬赏任务的采纳列表
     * @param Request $request
     * @return string
     */
    public function GetTaskAdoptList(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $task_list = DataComm::GetTaskAdoptList($id);
            $items = json_decode(json_encode($task_list),true);
            DataComm::SetFileUrl($items);
            $this->data['TaskList'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取任务订单的沟通列表
     * @param Request $request
     * @return string
     */
    public function GetTaskChatList(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $task = DB::table('view_task_list')->where('id',$id)->first();
            $this->data['TaskInfo']=$task;
            $chat = DB::table('view_task_chat_list')->where('task_id',$id)->orderBy('id','desc')->get();
            $this->data['TaskChat']=$chat;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 撤销任务订单
     * @param Request $request
     * @return string
     */
    public function CancelRewardOrder(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $order = RewardOrder::find($id);
            if(!empty($order)){
                DB::transaction(function ()use($order){
                    $order->isdelete = 1;
                    if($order->status != RewardOrderStatus::APPLY && $order->status != RewardOrderStatus::END){
                        DataComm::Increase(ReleaseEnum::REWARD,$order->reward_id,'number');
                    }
                    $order->save();
                });
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 我发布的悬赏任务
     * @param Request $request
     * @return string
     */
    public function GetMyReward(Request $request){
        try{
            $uid = auth()->id();
            $reward = DataComm::GetMyReward($uid);
            $this->data['MyReward'] = $reward->items();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 我申请的悬赏任务
     * @param Request $request
     * @return string
     */
    public function GetApplyReward(Request $request){
        try{
            $uid = auth()->id();
            $reward = DataComm::GetApplyReward($uid);
            $this->data['MyReward'] = $reward->items();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

}