<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/8
 * Time: 14:08
 * 付费商品
 */

namespace App\Http\Controllers;

use App\Lib\AccessEnum;
use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Lib\DataComm;
use App\Models\Goods;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsController extends Controller
{
    use ReturnData;
    /**
     * 编辑付费商品
     * @param Request $request
     * @return string
     */
    public function EditGoods(Request $request){
        try{
            $uid = auth()->id();
            $id = $request->input('id','');
            $main_url = $request->input('main_url',''); //主图
            $title = $request->input('title',''); //标题
            $content = $request->input('content','');//内容
            $is_plaza = $request->input('is_plaza',0); //是否发布到广场
            $address = $request->input('address',''); //所在地址
            $label_id = $request->input('label_id',0);//标签
            $amount = $request->input('amount',0); //数量
            $price = $request->input('price',0); //单价/积分
            $fare = $request->input('fare',0);  //运费
            $peak = $request->input('peak',0); //转卖上限
            $pay_type = $request->input('type',0);//商品类型：付费/积分
            if(empty($id)){
                $goods = new Goods();
                $goods->uid = $uid;
                $goods->price = $price;
                $goods->fare = $fare;
                $goods->peak = $peak;
                $goods->pay_type=$pay_type;
            }else{
                $goods = Goods::find($id);
                $goods->update_time = date("Y-m-d H:i:s");
            }
            $goods->main_url = $main_url;
            $goods->title = $title;
            $goods->content = $content;
            $goods->is_plaza = $is_plaza;
            $goods->label_id = $label_id;
            $goods->address = $address;
            $goods->amount = $amount;
            $goods->save();
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }


    /**
     * 转卖商品
     * @param Request $request
     * @return string
     */
    public function TurnGoods(Request $request){
        try{
            $uid =  auth()->id();
            $turn_id = $request->input('turn_id',0);
            $init_id = $request->input('init_id',0);
            $turn_price = $request->input('turn_price',0);
            $source = $request->input('source',0);
            if(empty($turn_id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '转卖商品id不能为空';
                return $this->toJson();
            }
            $goods =  new Goods();
            $goods->uid = $uid;
            $goods->type = DefaultEnum::YES;
            $goods->init_id = $init_id; //初始id
            $goods->turn_id = $turn_id;
            $goods->turn_price = $turn_price;
            DB::transaction(function() use($goods,$source){
                $goods->save();
                $turn = Goods::find($goods->turn_id);
                //保存转卖记录
                Turn::insert(
                    ['pro_type'=>ReleaseEnum::GOODS,
                        'pro_id'=>$goods->turn_id,
                        'uid'=>$goods->uid,
                        'issue_uid'=>$turn->uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                DataComm::Increase(ReleaseEnum::GOODS,$goods->turn_id,'turns');
            });
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取商品详情
     * @param Request $request
     * @return string
     */
    public function GetGoods(Request $request){
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = '商品id不能为空';
                return $this->toJson();
            }
            $goods = DataComm::GetGoodsInfo($id);
            if(empty($goods)){
                $this->code = ErrorCode::DATA_LOGIN;
                $this->message = '数据不存在';
                return $this->toJson();
            }
            //商品信息
            $this->data['Goods'] = $goods;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $this->data['IsLike'] =DataComm::IsLike(ReleaseEnum::GOODS,$goods->id,$uid);
            //评论信息
            $this->data['Comment'] = DataComm::GetComment(ReleaseEnum::GOODS,$goods->id);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取自己的商品列表
     * @param Request $request
     * @return string
     */
    public function GetGoodsList(Request $request){
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = auth()->id();

            //获取我的普通动态数据，每次显示10条
            $data_list = DataComm::GetGoodsList($uid);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $this->message = "最后一页了，没有数据了";
                return $this->toJson();
            }
            $items = json_decode(json_encode($data_list),true);
            foreach ($items as &$item){
                //是否点赞
                $item['islike'] = DataComm::IsLike(ReleaseEnum::GOODS,$item['id'],$uid);
            }
            $this->data['GoodsList'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 获取朋友圈付费商品列表
     * @param Request $request
     * @return string
     */
    public function GetCircleGoods(Request $request){
        try{
            $uid = auth()->id();
            //获取所有还有id和自己的id
            $circle_ids = DataComm::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list =DataComm::GetGoodsList($circle_ids);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            $items = json_decode(json_encode($data_list),true);
            foreach ($items as &$item){
                //是否点赞
                $item['islike'] = DataComm::IsLike(ReleaseEnum::GOODS,$item['id'],$uid);
            }
            $this->data['CircleGoods'] = $items;
            return $this->toJson();

        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 广场付费商品列表
     * @param Request $request
     * @return string
     */
    public function GetSquareGoods(Request $request){
        try{
            $uid = auth()->id();
            //获取广场付费商品列表，每次显示10条
            $data_list = DataComm::GetSquareGoodsList();
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $this->message = "最后一页，没有数据了";
                return $this->toJson();
            }
            $items = json_decode(json_encode($data_list),true);
            foreach ($items as &$item){
                //是否点赞
                $item['islike'] = DataComm::IsLike(ReleaseEnum::GOODS,$item['id'],$uid);
            }
            $this->data['SquareGoods'] = $items;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

    /**
     * 商品置顶/取消置顶
     * @param Request $request
     * @return string
     */
    public function ToppingGoods(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            $model = Goods::find($id);
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
     * 删除商品
     * @param Request $request
     * @return string
     */
    public function DeleteGoods(Request $request){
        try{
            $id = $request->input('id','');
            if(empty($id)){
                $this->code = ErrorCode::PARAM_ERROR;
                $this->message = 'id不能为空';
                return $this->toJson();
            }
            DB::table('pro_mall_goods')->where('id',$id)->update(['isdelete'=>1]);
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }

}