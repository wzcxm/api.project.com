<?php
/**
 * Created by PhpStorm.
 * User: YM
 * Date: 2019/2/15
 * Time: 11:30
 */

namespace App\Http\Controllers;

use App\Lib\Common;
use App\Lib\FundsEnum;
use App\Models\Order;
use App\Models\PayRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yansongda\LaravelPay\Facades\Pay;
use Illuminate\Http\Request;

class PayController extends Controller
{

    /**
     * 支付宝回调
     * @param Request $request
     * @return mixed
     */
    public function AliNotify(Request $request){
        try{
            $data = Pay::alipay()->verify(); // 是的，验签就这么简单！
            //付款成功，回写订单状态
            if($data->trade_status=='TRADE_SUCCESS' || $data->trade_status=='TRADE_FINISHED'){
                $pay_sn = $data->out_trade_no;//订单号
                DB::transaction(function ()use($pay_sn){
                    //获取保存的支付信息
                    $pay_record = PayRecord::where('pay_no',$pay_sn)->first();
                    if(!empty($pay_record)){
                        if($pay_record->pro_type==1){ //购买商品
                            self::UpdateOrder($pay_record->pro_id);
                        }
                        //更新支付信息状态为付款成功
                        DB::table('pro_mall_payrecord')->where('pay_no',$pay_sn)->update(['status'=>1]);
                    }
                });
            }else{
                Log::info('ali_notify:'.$data->all()) ;
            }
        } catch (\Exception $e) {
            Log::error('ali_pay:'.$e->getMessage()) ;
        }
        return Pay::alipay()->success();
    }

    /**
     * 微信支付回调
     * @param Request $request
     * @return mixed
     */
    public function WeChatNotify(Request $request){
        try{
            $data = Pay::wechat()->verify(); // 是的，验签就这么简单！
            //付款成功，回写订单状态
            if($data->return_code=='SUCCESS' && $data->result_code=='SUCCESS'){
                $pay_sn = $data->out_trade_no;//订单号
                DB::transaction(function ()use($pay_sn){
                    //获取保存的支付信息
                    $pay_record = PayRecord::where('pay_no',$pay_sn)->first();
                    if(!empty($pay_record)){
                        if($pay_record->pro_type==1){ //购买商品
                            self::UpdateOrder($pay_record->pro_id);
                        }
                        //更新支付信息状态为付款成功
                        DB::table('pro_mall_payrecord')->where('pay_no',$pay_sn)->update(['status'=>1]);
                    }
                });
            }else{
                Log::info('wechat_notify:'.$data->all()) ;
            }
        } catch (\Exception $e) {
            Log::error('wechat_pay:'.$e->getMessage()) ;
        }
        return Pay::wechat()->success();
    }


    /**
     * 支付成功后，更新订单及商品库存
     * @param $order_id
     */
    private  function UpdateOrder($order_id){
        try{
            DB::transaction(function ()use($order_id){
                //更新订单为已付款
                $order = Order::find($order_id);
                if(!empty($order)){
                    DB::table('pro_mall_order')->where('sn',$order->sn)->update(['status'=>1]);
                    //更新商品库存
                    DB::table('pro_mall_goods')->where('id',$order->turn_id)->decrement('amount',$order->num);
                    //如果使用钱包付款，扣除钱包金额
                    if($order->purse>0){
                        DB::table('pro_mall_wallet')->where('uid',$order->buy_uid)->decrement('amount',$order->purse);
                    }
                    //保存资金流水记录
                    Common::SaveFunds($order->buy_uid, FundsEnum::BUY, $order->pay_amount+$order->purse, $order->sn, '购买商品', 1,$order->id);
                }
            });
        }catch (\Exception $e){
            Log::error('Up_Order:'.$e->getMessage());
        }
    }
}

