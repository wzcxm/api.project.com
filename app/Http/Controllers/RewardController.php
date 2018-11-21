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
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\ErrorCode;
use App\Models\Reward;
use App\Models\Turn;
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
            $title = $request->input('title','');
            $remark = $request->input('remark','');
            $number = $request->input('number',0);
            $label = $request->input('label',0);
            $address = $request->input('address','');
            $access = $request->input('access',0);
            $issquare = $request->input('issquare',0);
            $visible_uids = $request->input('visible_uids','');
            $bounty = $request->input('bounty',0);
            $hope_time = $request->input('hope_time',0);
            $files = $request->input('files','');
            if(empty($id)){
                $reward = new Reward();
                $reward->uid = $uid;
            }else{
                $reward = Reward::find($id);
                $reward->update_time = date("Y-m-d H:i:s");
            }
            $reward->title = $title;
            $reward->remark = $remark;
            $reward->number = $number;
            $reward->label = $label;
            $reward->address = $address;
            $reward->bounty = $bounty;
            $reward->price = $bounty/$number;
            $reward->hope_time = $hope_time;
            if($issquare == DefaultEnum::YES){
                $reward->issquare = DefaultEnum::YES;
                $reward->access = AccessEnum::PUBLIC;
            }else{
                $reward->issquare = DefaultEnum::NO;
                $reward->access = $access;
                if($access == AccessEnum::PARTIAL){          //如果是部分用户可见，则保存可见用户（数组形式）
                    $reward->visible_uids = explode('|',$visible_uids);
                }
            }
            if(!empty($files)){
                $reward->isannex = DefaultEnum::YES;
            }
            DB::transaction(function() use($reward,$files){
                $reward->save();
                if(!empty($files)){
                    //保存文件地址
                    DataComm::SaveFiles(ReleaseEnum::REWARD,$reward->id,$files);
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
     * 转发悬赏任务
     * @param Request $request
     * @return string
     */
    public function TurnReward(Request $request){
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '悬赏任务id不能为空';
                return $this->toJson();
            }
            $reward =  new Reward();
            $reward->uid = $uid;
            $reward->type = DefaultEnum::YES;
            $reward->front_id = $front_id;
            $turn_reward = Reward::find($front_id);
            $issue_uid = $turn_reward->uid;
            DB::transaction(function() use($reward,$source,$issue_uid){
                $reward->save();
                //保存转卖记录
                Turn::insert(
                    ['release_type'=>ReleaseEnum::REWARD,
                        'release_id'=>$reward->front_id,
                        'uid'=>$reward->uid,
                        'issue_uid'=>$issue_uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                DataComm::Increase(ReleaseEnum::REWARD,$reward->front_id,'turnnum');
            });
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
            $reward = DataComm::GetRewardInfo($id);
            if(empty($reward)){
                $this->code = ErrorCode::DATA_LOGIN;
                $this->message = '数据不存在';
                return $this->toJson();
            }
            //添加文件地址
            if($reward->type == DefaultEnum::NO){
                //如有文件，加入文件地址
                if($reward->isannex == DefaultEnum::YES){
                    $reward->files = DataComm::GetFiles(ReleaseEnum::REWARD,$id);
                }
                //当前用户的悬赏任务订单信息
                $order = DataComm::GetRewardOrder($id,$uid);
                if(!empty($order)){
                    if($order->isannex == DefaultEnum::YES){
                        $order->files = DataComm::GetFiles(ReleaseEnum::REWARD_ORDER,$id);
                    }
                    $reward->order =$order;
                }
            }else{
                unset($reward->isannex);
                unset($reward->title);
                unset($reward->remark);
                unset($reward->number);
                unset($reward->bounty);
                unset($reward->price);
                unset($reward->hope_time);
                unset($reward->label_name);
                unset($reward->address);
                $turn = DataComm::GetRewardInfo($reward->front_id);
                if(!empty($turn)){
                    //如有文件，加入文件地址
                    if($turn->isannex == DefaultEnum::YES){
                        $turn->files = DataComm::GetFiles(ReleaseEnum::REWARD,$turn->id);
                    }
                    $reward->turn = $turn;
                }
            }
            //任务信息
            $this->data['Reward'] = $reward;
            //当前查看用户是否点赞
            $this->data['IsLike'] =DataComm::IsLike(ReleaseEnum::REWARD,$reward->id,$uid);
            //评论信息
            $this->data['Comment'] = DataComm::GetComment(ReleaseEnum::REWARD,$reward->id);
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
            $uid = $request->input('find_uid',auth()->id());

            //获取我的普通动态数据，每次显示10条
            $data_list = DataComm::GetRewardList($uid);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加文件访问地址
            DataComm::SetFileUrl($items,ReleaseEnum::GOODS);
            $this->data['RewardList'] = $items;
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
            //获取所有还有id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list =DataComm::GetRewardList($circle_ids);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //去除没有权限的商品
            DataComm::FilterRelease($items,$uid);
            //添加文件访问地址
            DataComm::SetFileUrl($items,ReleaseEnum::GOODS);
            $this->data['CircleReward'] = $items;
            return $this->toJson();

        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}