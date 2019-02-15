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
use Yansongda\LaravelPay\Facades\Pay;

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
            if(empty($id)){
                $reward = new Reward();
                $reward->sn = Common::CreateCode();
                $reward->uid = $uid;
                $reward->amount = $amount;
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
            $reward->hope_time = $hope_time;
            //保存任务
            DB::transaction(function ()use($reward,$id){
                $reward->save();
                //发布新任务时，保存资金流水记录
                if(empty($id)){
                    Common::SaveFunds($reward->uid,
                        FundsEnum::RELEASE,
                        $reward->total,
                        $reward->id,
                        '发布任务：'.$reward->title,
                        1,
                        $reward->id);
                }
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    public function RewardPay(Request $request){
        try{
            $id = $request->input('id','');
            $pay_type = $request->input('pay_type',''); //支付号
            $total = $request->input('total',''); //总支付金额
            $purse = $request->input('purse',''); //钱包支付金额
            $pay_amount = $request->input('pay_amount',''); //第三方支付金额
            $pay_pwd = $request->input('pay_pwd','');
            $reward = Reward::find($id);
            if(empty($reward)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id错误';
                return $this->toJson();
            }
            $reward->purse = $purse;
            $reward->total = $total;
            $reward->pay_amount = $pay_amount;
            ///钱包支付
            if($pay_amount == 0 && $purse > 0){
                $user = auth()->user();
                if(empty($user->pay_pwd)){
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = '未设置支付密码';
                    return $this->toJson();
                }
                if($user->pay_pwd != md5($pay_pwd)){
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = '支付密码错误';
                    return $this->toJson();
                }
                DB::transaction(function ()use($reward,$user) {
                    DB::table('pro_mall_wallet')->where('uid', $user->uid)->decrement('amount', $reward->purse);
                    //保存资金流水记录
                    Common::SaveFunds($user->uid, FundsEnum::RELEASE, $reward->purse, $reward->sn, '购买商品', 1, $reward->id);
                    $reward->pay_status = 1;
                    $reward->save();
                    $this->message = '钱包支付成功！';
                });
            }else{ //第三方支付
                $pay_sn = Common::CreateCode();
                $this->data['PayJson'] = Common::CommPay($pay_type,$pay_sn,$pay_amount,'发布任务');
                $reward->pay_no = $pay_sn;
                //支付记录
                $pay_record = ['pro_type'=>2, 'uid'=>auth()->id(), 'pro_id'=>$id, 'pay_no'=>$pay_sn, 'pay_type'=>$pay_type, 'amount'=>$pay_amount];
                DB::transaction(function ()use($reward,$pay_record){
                    $reward->save();
                    //保存支付信息
                    DB::table('pro_mall_payrecord')->insert($pay_record);
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
            }DB::transaction(function ()use($id){
                $reward = Reward::find($id);
                $reward->isdelete=1;
                $reward->save();
                //退回赏金
                //总赏金 - 已发放赏金 = 需退回赏金
                $ret_money = $reward->total-$reward->surplus;
                if($ret_money > 0){
                    DB::table('pro_mall_wallet')->where('uid',$reward->uid)->increment('amount', $ret_money);
                    //保存资金流水记录
                    Common::SaveFunds($reward->uid, FundsEnum::RELEASE, $ret_money, '', '退回赏金：'.$reward->title, 0,$id);
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
            $reward = Reward::find($r_id);
            if(!empty($reward) && $reward->status>=2){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '该任务已完成或者已过期！';
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
                        if(empty($task)){
                            $this->code = ErrorCode::PARAM_ERROR;
                            $this->message = 'id错误！';
                            return $this->toJson();
                        }
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
                        if(empty($task)){
                            $this->code = ErrorCode::PARAM_ERROR;
                            $this->message = 'id错误！';
                            return $this->toJson();
                        }
                        $task->status = TaskStatus::COMPLY;
                        $task->submit_time = date("Y-m-d H:i:s");
                        $task->save();
                        break;
                    case TaskStatus::COMPLETED:  //完成
                        $task = Task::find($id);
                        if(empty($task)){
                            $this->code = ErrorCode::PARAM_ERROR;
                            $this->message = 'id错误！';
                            return $this->toJson();
                        }
                        $task->status = TaskStatus::COMPLETED;
                        $task->end_time = date("Y-m-d H:i:s");
                        $task->save();
                        //支付佣金
                        DB::table('pro_mall_wallet')->where('uid',$task->uid)->increment('amount', $task->price);
                        //保存资金流水记录
                        Common::SaveFunds($task->uid, FundsEnum::FINISH, $task->price, $task->id, '获得佣金', 0,$task->id);
                        //回写，任务已付赏金数量
                        DB::table('pro_mall_reward')->where('id',$task->r_id)->increment('surplus', $task->price);
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