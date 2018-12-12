<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/19
 * Time: 14:16
 */

namespace App\Http\Controllers;
use App\Lib\AccessEnum;
use App\Lib\DataComm;
use App\Lib\DefaultEnum;
use App\Lib\FundsEnum;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\ErrorCode;
use App\Lib\RewardOrderStatus;
use App\Models\Reward;
use App\Models\RewardOrder;
use App\Models\Turn;
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
                $reward->update_time = date("Y-m-d H:i:s");
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
            if(count($reward)<=0){
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
            $page = $request->input('page',0);
            //获取我的任务数据，每次显示10条
            $data_list = DataComm::GetRewardList($uid,1,'',$page);
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
            $page = $request->input('page',0);
            //获取所有还有id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            $uid_arr = implode(",", $circle_ids);
            //获取圈子普通动态数据，每次显示10条
            $data_list =DataComm::GetRewardList($uid,3,$uid_arr,$page);
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
            $page = $request->input('page',0);
            //获取广场悬赏任务列表，每次显示10条
            $data_list = DataComm::GetRewardList($uid,2,'',$page);
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
            $reward = Reward::find($id);

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
            $id = $request->input('id',0);
            $price = $request->input('price',0);
            $ask = $request->input('ask','');
            $files = $request->input('files','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $rewardOrder = new RewardOrder();
            $rewardOrder->reward_id = $id;
            $rewardOrder->ask = $ask;
            $rewardOrder->uid = $uid;
            $rewardOrder->price = $price;
            if(!empty($files)){
                $rewardOrder->isannex = DefaultEnum::YES;
            }
            DB::transaction(function ()use($rewardOrder,$files){
                $rewardOrder->save();
                if(!empty($files)){
                    //保存文件地址
                    DataComm::SaveFiles(ReleaseEnum::REWARD_ORDER,$rewardOrder->id,$files);
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
    public function GetMyApplyReward(Request $request){
        try{
            $uid = auth()->id();
            $reward = DataComm::GetMyApplyReward($uid);
            $this->data['MyReward'] = $reward->items();
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
    public function GetRewardOrderList(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '任务id不能为空';
                return $this->toJson();
            }
            $order = DataComm::GetRewardOrderList($id);
            $this->data['RewardOrderList'] = $order;
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
    public function SetRewardOrder(Request $request){
        try{
            $id = $request->input('id',0);
            $status = $request->input('status',0);
            if(empty($id) || empty($status)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id或status不能为空';
                return $this->toJson();
            }
            $order = RewardOrder::find($id);
            $order->status = $status;
            DB::transaction(function ()use($order){
                $order->save();
                //采纳操作，采纳后悬赏任务数量减1
                if($order->status == RewardOrderStatus::ACCEPT){
                    DataComm::Decrement(ReleaseEnum::REWARD,$order->reward_id,'number');
                }
                //确认完成操作，给接单人转佣金
                if($order->status == RewardOrderStatus::END){
                   $wallet = Wallet::firstOrCreate(['uid'=>$order->uid]);
                   $wallet->can_amount += $order->price;
                   $wallet->save();
                   //保存资金流水
                   $funds = [
                     'uid'=>$order->uid,
                       'type'=>FundsEnum::FINISH,
                       'amount'=>$order->price,
                       'balance'=>$wallet->can_amount,
                       'pro_id'=>$order->reward_id,
                       'pro_name'=>'完成任务佣金',
                       'inorout'=>1
                   ];
                   DB::table('pro_mall_funds')->insert($funds);
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

}