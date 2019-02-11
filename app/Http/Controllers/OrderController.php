<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/3
 * Time: 9:27
 * 订单
 */

namespace App\Http\Controllers;

use App\Lib\Common;
use App\Lib\DefaultEnum;
use App\Lib\FundsEnum;
use App\Models\Order;
use function foo\func;
use Illuminate\Http\Request;
use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ReturnData;

    /**
     * 新增订单
     * @param Request $request
     * @return string
     */
    public function AddOrder(Request $request){
        try{
            $order =  new Order();
            $order->sn = Common::CreateCode();
            $order->buy_uid = auth()->id();
            $order->type = $request->input('type',0); //商品类型：0-付费；1-积分
            $order->is_turn = $request->input('is_turn',0); //是否转卖商品：0-原创；1-转卖
            $order->g_id = $request->input('g_id',0); //商品id
            $order->g_uid = $request->input('g_uid',0); //商品发布人uid
            $order->num = $request->input('num',0); //购买数量
            $order->address = $request->input('address',0); //收货地址id
            $order->g_amount = $request->input('g_amount',0); //商品总价/积分
            $order->fare = $request->input('fare',0); //运费
            $order->total = $request->input('total',0); //订单总金额
            $order->purse = $request->input('purse',0); //钱包支付金额
            $order->pay_amount = $request->input('pay_amount',0); //微信/支付宝支付金额
            $order->pay_sn = $request->input('pay_sn',''); //微信/支付宝支付流水号
            DB::transaction(function()use($order){
                //保存订单信息
                $order->save();
                //保存资金流水记录
                if($order->total>0){
                    Common::SaveFunds($order->buy_uid, FundsEnum::BUY, $order->total, $order->sn, '购买商品', 1,$order->g_id);
                }
                $this->data['order_id'] = $order->id;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 快递查询
     * @param Request $request
     * @return string
     */
    public function FindExpress(Request $request){
        try{
            $com = $request->input('com','');
            $num = $request->input('num','');
            if(empty($com) || empty($num)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '快递公司编码或快递单号不能为空';
                return $this->toJson();
            }
            $data = Common::Find_Express($com,$num);
            $this->data = $data;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 我卖出的列表
     * @param Request $request
     * @return string
     */
    public function MySellOrder(Request $request){
        try{
            $uid = auth()->id();
            $status = $request->input('status',1);
            //订单各个状态数量
            $this->data['Status_Num'] = [
                'pending'=> Common::GetOrderNum($uid,1,1),
                'sent'=> Common::GetOrderNum($uid,2,1),
                'cancel'=> Common::GetOrderNum($uid,4,1),
                'finish'=> Common::GetOrderNum($uid,3,1)
            ];
            //我卖出的订单列表
            $order = DB::table('view_order_list')
                    ->where('g_uid',$uid)
                    ->where('status',$status)
                    ->orderBy('id','desc')
                    ->simplePaginate(10);
            $order = $order->items();
            $this->data['Sell_List'] = $order;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 我购买的列表
     * @param Request $request
     * @return string
     */
    public function MyBuyOrder(Request $request){
        try{
            $uid = auth()->id();
            $status = $request->input('status',1);
            //订单各个状态数量
            $this->data['Status_Num'] = [
                'pending'=> Common::GetOrderNum($uid,1,2),
                'sent'=> Common::GetOrderNum($uid,2,2),
                'cancel'=> Common::GetOrderNum($uid,4,2),
                'finish'=> Common::GetOrderNum($uid,3,2)
            ];
            //我买到的订单列表
            $order = DB::table('view_order_list')
                ->where('buy_uid',$uid)
                ->where('status',$status)
                ->where('is_turn',0)
                ->where('isdelete',0)
                ->orderBy('id','desc')
                ->simplePaginate(10);
            $order = $order->items();
            $this->data['Buy_List'] = $order;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 删除订单
     * @param Request $request
     * @return string
     */
    public function DelOrder(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id不能为空';
                return $this->toJson();
            }
            $order = Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id错误';
                return $this->toJson();
            }
            //关闭或者完成的订单可以删除
            if($order->status == 3 || $order->status == 4){
                $order->isdelete = 1;
                $order->save();
            }else{
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单未完成或未关闭，不能删除';
                return $this->toJson();
            }
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return string
     */
    public function GetOrder(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id不能为空';
                return $this->toJson();
            }
            $order = DB::table('view_order_info')->where('id',$id)->first();
            $this->data = $order;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 订单发货
     * @param Request $request
     * @return string
     */
    public function Ship(Request $request){
        try{
            $id = $request->input('id',0);
            $express = $request->input('express',0);
            $firm = $request->input('firm','');
            $firm_code = $request->input('firm_code',0);
            $order = Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id错误';
                return $this->toJson();
            }
            //修改订单状态为已发货
            DB::table('pro_mall_order')
                ->where('sn',$order->sn)
                ->update(['express'=>$express,'firm'=>$firm,'firm_code'=>$firm_code,'hair_time'=>date("Y-m-d H:i:s"),'status'=>2]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 关闭订单
     * @param Request $request
     * @return string
     */
    public function CloseOrder(Request $request){
        try{
            $id = $request->input('id',0);
            $order = Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id错误';
                return $this->toJson();
            }
            DB::transaction(function()use($order){
                //修改订单状态为关闭
                DB::table('pro_mall_order')->where('sn',$order->sn)->update(['status'=>4]);
                //退回订单金额
                if($order->total>0){
                    DB::table('pro_mall_wallet')->where('uid',$order->buy_uid)->increment('amount', $order->total);
                    //保存资金流水记录
                    Common::SaveFunds($order->buy_uid, FundsEnum::BUY, $order->total, $order->sn, '退回商品金额', 0,$order->id);
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
     * 确认收货
     * @param Request $request
     * @return string
     */
    public function ConfirmOrder(Request $request){
        try{
            $id = $request->input('id',0);
            $order = Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id错误';
                return $this->toJson();
            }
            DB::transaction(function()use($order){
                //修改订单状态为完成
                DB::table('pro_mall_order')->where('sn',$order->sn)->update(['status'=>3,'finish_time'=>date("Y-m-d H:i:s")]);
                //商家增加货款
                DB::table('pro_mall_wallet')->where('uid',$order->g_uid)->increment('amount', $order->total);
                //积分商品，给商家增加积分
                if($order->type==1){
                    DB::table('pro_mall_users')->where('uid',$order->g_uid)->increment('integral', $order->g_amount);
                }
                //保存资金流水记录
                if($order->total>0){
                    Common::SaveFunds($order->g_uid, FundsEnum::SELL, $order->total, $order->sn, '卖出商品', 0,$order->id);
                }
            });

            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}