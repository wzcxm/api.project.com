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
use App\Models\Goods;
use App\Models\Order;
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
            //$order->turn_id = $request->input('init_id',0); //转卖商品原始id
            $order->g_id = $request->input('g_id',0); //商品id
            $order->g_uid = $request->input('g_uid',0); //商品发布人uid
            $order->num = $request->input('num',0); //购买数量
            $order->g_amount = $request->input('g_amount',0); //商品总价/积分
            $order->fare = $request->input('fare',0); //运费
            $order->total = $request->input('total',0); //订单总金额
            $goods = Goods::find($order->g_id);
            if(!empty($goods)){
                if($goods->amount-$order->num<0){
                    $this->code = ErrorCode::PARAM_ERROR;
                    $this->message = '商品库存不足';
                    return $this->toJson();
                }
                //应得金额
                if($order->is_turn == DefaultEnum::YES){  //转卖商品
                    $front = Goods::find($order->turn_id);
                    if(!empty($front)){//上级应得
                        $order->deserve = $order->g_amount - $front->price * $order->num;
                    }
                }else{    //原创商品
                    $order->deserve = $order->g_amount;
                }
            }

            DB::transaction(function()use($order,$goods){
                //保存订单信息
                $order->save();
                //保存资金流水记录
                if($order->total>0){
                    Common::SaveFunds($order->buy_uid, FundsEnum::BUY, $order->total, $order->sn, '购买商品', 1,$order->g_id);
                }
                //转卖订单，生产上级订单
                if($order->is_turn == DefaultEnum::YES){
                    $goods = Goods::find($order->g_id);
                    if(!empty($goods)){
                        Common::Create_Order($order,$goods->turn_id);
                    }
                }
                $this->data['order_id'] = $order->id;
                $this->data['order_sn'] = $order->sn;
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 订单付款
     * @param Request $request
     * @return string
     */
    public function Pay(Request $request){
        try{
            $id = $request->input('id',0);
            $address = $request->input('address',0);//地址id
            $purse = $request->input('purse',0);//钱包支付金额
            $pay_amount = $request->input('pay_amount',0);//第三方支付金额
            $pay_type = $request->input('pay_type',0);//支付方式：0-微信；1-支付宝
            $order  =Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id错误';
                return $this->toJson();
            }
            $goods = Goods::find($order->g_id);
            if($goods->amount-$order->num<0){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '商品库存不足';
                return $this->toJson();
            }
            $order->address = $address;
            $order->purse = $purse;
            $order->pay_amount = $pay_amount;
            $pay_sn = Common::CreateCode();
            if($pay_type==DefaultEnum::YES){
                //调支付宝支付
                $this->data['PayJson'] = [];
            }else{
                //调微信支付
                $this->data['PayJson'] = [];
            }
            $order->pay_sn = $pay_sn;
            $order->save();
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
            $num = $request->input('num','');
            if( empty($num)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '快递公司编码或快递单号不能为空';
                return $this->toJson();
            }
            $data = Common::Find_Express($num);
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
            $express = $request->input('express','');
            $firm = $request->input('firm','');
            $order = Order::find($id);
            if(empty($order)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '订单id错误';
                return $this->toJson();
            }
            //修改订单状态为已发货
            DB::table('pro_mall_order')
                ->where('sn',$order->sn)
                ->update(['express'=>$express,'firm'=>$firm,'hair_time'=>date("Y-m-d H:i:s"),'status'=>2]);
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
                $up_arr = ['status'=>4];
                $uid = auth()->id();
                if($uid == $order->buy_uid){
                    $up_arr['close_type']=0;
                }else{
                    $up_arr['close_type']=1;
                }
                DB::table('pro_mall_order')->where('sn',$order->sn)->update($up_arr);
                //退回订单金额
                if($order->total>0){
                    DB::table('pro_mall_wallet')->where('uid',$order->buy_uid)->increment('amount', $order->total);
                    //保存资金流水记录
                    Common::SaveFunds($order->buy_uid, FundsEnum::BUY, $order->total, $order->sn, '商品退款', 0,$order->id);
                }
                //退回积分
                if($order->type == 1){
                    DB::table('pro_mall_users')->where('uid',$order->buy_uid)->increment('integral', $order->g_amount);
                }
                //订单关闭后，商品数量加上订单数量
                DB::table('pro_mall_goods')->where('id',$order->g_id)->increment('amount',$order->num);
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
                $order_list = DB::table('pro_mall_order')->where('sn',$order->sn)->get();
                //修改订单状态为完成
                DB::table('pro_mall_order')->where('sn',$order->sn)->update(['status'=>3,'finish_time'=>date("Y-m-d H:i:s")]);
                if(count($order_list)>1){
                    foreach ($order_list as $item){
                        if($item->is_turn == DefaultEnum::YES){   //转卖商品
                            if($item->type == 1){ //积分商品
                                DB::table('pro_mall_users')->where('uid',$item->g_uid)->increment('integral', $item->deserve);
                            }else{
                                DB::table('pro_mall_wallet')->where('uid',$item->g_uid)->increment('amount', $item->deserve);
                                Common::SaveFunds($item->g_uid, FundsEnum::SELL, $item->deserve, $item->sn, '卖出商品', 0,$item->id);
                            }
                        }else{   //原创商品
                            //增加积分
                            if($item->type == 1){
                                DB::table('pro_mall_users')->where('uid',$item->g_uid)->increment('integral', $item->deserve);
                                //积分商品，如果有运费，给商家付运费
                                if($item->fare>0){
                                    DB::table('pro_mall_wallet')->where('uid',$item->g_uid)->increment('amount', $item->fare);
                                    Common::SaveFunds($item->g_uid, FundsEnum::SELL, $item->fare, $item->sn, '卖出商品', 0,$item->id);
                                }
                            }else{
                                //商家付货款
                                $amount = $item->deserve + $item->fare;
                                DB::table('pro_mall_wallet')->where('uid',$item->g_uid)->increment('amount', $amount);
                                Common::SaveFunds($item->g_uid, FundsEnum::SELL, $amount, $item->sn, '卖出商品', 0,$item->id);
                            }
                        }
                    }
                }else{
                    //保存资金流水记录
                    if($order->total>0){
                        //商家增加货款
                        DB::table('pro_mall_wallet')->where('uid',$order->g_uid)->increment('amount', $order->total);
                        Common::SaveFunds($order->g_uid, FundsEnum::SELL, $order->total, $order->sn, '卖出商品', 0,$order->id);
                    }
                    //积分商品，给商家增加积分
                    if($order->type==1){
                        DB::table('pro_mall_users')->where('uid',$order->g_uid)->increment('integral', $order->g_amount);
                    }
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