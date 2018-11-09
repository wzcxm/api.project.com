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
use App\Lib\Common;
use App\Lib\DefaultEnum;
use App\Lib\ErrorCode;
use App\Lib\ReleaseEnum;
use App\Lib\ReturnData;
use App\Models\Goods;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsController extends Controller
{

    /**
     * 编辑付费商品
     * @param Request $request
     * @return string
     */
    public function EditGoods(Request $request){
        $retJson = new ReturnData();
        try{
            $uid = auth()->id();
            $id = $request->input('id','');
            $name = $request->input('name','');
            $remark = $request->input('remark','');
            $number = $request->input('number',0);
            $label = $request->input('label',0);
            $address = $request->input('address','');
            $access = $request->input('access',0);
            $issquare = $request->input('issquare',0);
            $visible_uids = $request->input('visible_uids','');
            $price = $request->input('price',0);
            $firstprice = $request->input('firstprice',0);
            $fare = $request->input('fare',0);
            $limit = $request->input('limit',0);
            $files = $request->input('files','');
            if(empty($id)){
                $goods = new Goods();
                $goods->uid = $uid;
                $goods->price = $price;
                $goods->firstprice = $firstprice;
                $goods->fare = $fare;
                $goods->limit = $limit;
            }else{
                $goods = Goods::find($id);
                $goods->update_time = date("Y-m-d H:i:s");
            }
            $goods->name = $name;
            $goods->remark = $remark;
            $goods->number = $number;
            $goods->label = $label;
            $goods->address = $address;
            if($issquare == DefaultEnum::YES){
                $goods->issquare = DefaultEnum::YES;
                $goods->access = AccessEnum::PUBLIC;
            }else{
                $goods->access = $access;
                if($access == AccessEnum::PARTIAL){          //如果是部分用户可见，则保存可见用户（数组形式）
                    $goods->visible_uids = explode('|',$visible_uids);
                }
            }
            if(!empty($files)){
                $goods->isannex = DefaultEnum::YES;
            }
            DB::transaction(function() use($goods,$files){
                $goods->save();
                if(!empty($files)){
                    //保存文件地址
                    Common::SaveFiles(ReleaseEnum::GOODS,$goods->id,$files);
                }
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 转卖商品
     * @param Request $request
     * @return string
     */
    public function TurnGoods(Request $request){
        $retJson = new ReturnData();
        try{
            $uid =  auth()->id();
            $front_id = $request->input('turn_id',0);
            $turnprice = $request->input('turnprice',0);
            $source = $request->input('source',0);
            if(empty($front_id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '转卖商品id不能为空';
                return $retJson->toJson();
            }
            $goods =  new Goods();
            $goods->uid = $uid;
            $goods->buytype = DefaultEnum::YES;
            $goods->front_id = $front_id;
            $goods->turnprice = $turnprice;
            $turn_goods = Goods::find($front_id);
            if($turn_goods->buytype == DefaultEnum::NO){
                $goods->first_id = $front_id;
            }else{
                $goods->first_id = $turn_goods->first_id;
            }
            $issue_uid = $turn_goods->uid;
            DB::transaction(function() use($goods,$source,$issue_uid){
                $goods->save();
                //保存转卖记录
                Turn::insert(
                    ['release_type'=>ReleaseEnum::GOODS,
                        'release_id'=>$goods->front_id,
                        'uid'=>$goods->uid,
                        'issue_uid'=>$issue_uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                Common::Increase(ReleaseEnum::GOODS,$goods->front_id,'turnnum');
            });
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 获取商品详情
     * @param Request $request
     * @return string
     */
    public function GetGoods(Request $request){
        $retJson = new ReturnData();
        try{
            $id = $request->input('id',0);
            if(empty($id)){
                $retJson->code = ErrorCode::PARAM_ERROR;
                $retJson->message = '商品id不能为空';
                return $retJson->toJson();
            }
            $goods = DB::table('v_goods_info') ->where('id',$id)->first();
            if(empty($goods)){
                $retJson->code = ErrorCode::DATA_LOGIN;
                $retJson->message = '数据不存在';
                return $retJson->toJson();
            }
            $ret_goods = [
                'id'=>$goods->id,
                'uid'=>$goods->uid,
                'nickname'=>$goods->nickname,
                'head_url'=>$goods->head_url,
                'create_time'=>$goods->create_time
                ];
            //原创商品
            if($goods->buytype == DefaultEnum::NO){
                $ret_goods['price'] = $goods->price;
                self::SetGoods($ret_goods,$goods);
            }else{   //转卖商品
                if(!empty($goods->first_id)){
                    $turn_goods = DB::table('v_goods_info')->where('id',$goods->first_id)->first();
                    if(!empty($turn_goods)){
                        $ret_goods['price'] = $goods->turnprice;
                        self::SetGoods($ret_goods,$turn_goods);
                    }
                }
            }
            //商品信息
            $retJson->data['Goods'] = $ret_goods;
            //当前查看用户是否点赞
            $uid = auth()->id();
            $retJson->data['IsLike'] =Common::IsLike(ReleaseEnum::GOODS,$goods->id,$uid);
            //评论信息
            $retJson->data['Comment'] = Common::GetComment(ReleaseEnum::GOODS,$goods->id);
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

    //设置付费商品返回数据
    private function SetGoods(&$ret_goods,$goods){
        $ret_goods['name'] = $goods->name; //商品名称
        $ret_goods['remark'] = $goods->remark; //描述
        $ret_goods['number'] = $goods->number; //库存数量
        $ret_goods['label_name'] = $goods->label_name; //标签
        $ret_goods['address'] = $goods->address; //地址
        $ret_goods['firstprice'] = $goods->firstprice; //原价
        $ret_goods['fare'] = $goods->fare;  //运费
        $ret_goods['limit'] = $goods->limit; //转卖上限
        $ret_goods['turn_num'] = $goods->turn_num; //转卖次数
        $ret_goods['like_num'] = $goods->like_num; //点赞次数
        $ret_goods['discuss_num'] = $goods->discuss_num; //评论次数
        $ret_goods['sell_num'] = $goods->sell_num;  //销量
        if($goods->isannex == DefaultEnum::YES){
            $ret_goods['files'] = Common::GetFiles(ReleaseEnum::GOODS,$goods->id); //图片地址
        }
    }

}