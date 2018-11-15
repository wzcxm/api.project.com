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
use App\Models\Files;
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
            $title = $request->input('title','');
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
            $goods->title = $title;
            $goods->remark = $remark;
            $goods->number = $number;
            $goods->label = $label;
            $goods->address = $address;
            if($issquare == DefaultEnum::YES){
                $goods->issquare = DefaultEnum::YES;
                $goods->access = AccessEnum::PUBLIC;
            }else{
                $goods->issquare = DefaultEnum::NO;
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
                        'release_id'=>$goods->first_id,
                        'uid'=>$goods->uid,
                        'issue_uid'=>$issue_uid,
                        'source'=>$source]);
                //该条动态增加一次转卖
                Common::Increase(ReleaseEnum::GOODS,$goods->first_id,'turnnum');
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
            $goods = DB::table('v_goods_info')->where('id',$id)->first();
            if(empty($goods)){
                $retJson->code = ErrorCode::DATA_LOGIN;
                $retJson->message = '数据不存在';
                return $retJson->toJson();
            }
            //添加文件地址
            if(!empty($goods->file_id)){
                $goods->files = Common::GetFiles(ReleaseEnum::GOODS,$goods->file_id);
            }
            //商品信息
            $retJson->data['Goods'] = $goods;
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

    /**
     * 获取自己的付费商品列表
     * @param Request $request
     * @return string
     */
    public function GetGoodsList(Request $request){
        $retJson = new ReturnData();
        try{
            //$find_uid不为空时，表示查询该用户的动态列表
            $uid = $request->input('find_uid',auth()->id());

            //获取我的普通动态数据，每次显示10条
            $data_list = DB::table('v_goods_list')->where('uid',$uid)
                ->orderBy('id','desc')->simplePaginate(10);
            $data_list = $data_list->items();
            if(count($data_list)<=0){
                $retJson->message = "最后一页了，没有数据了";
                return $retJson->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //添加文件访问地址
            Common::SetFileUrl($items,ReleaseEnum::GOODS);
            $retJson->data['GoodsList'] = $items;
            return $retJson->toJson();
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }


    /**
     * 获取朋友圈付费商品列表
     * @param Request $request
     * @return string
     */
    public function GetCircleGoods(Request $request){
        $retJson =  new ReturnData();
        try{
            $uid = auth()->id();
            //获取所有还有id和自己的id
            $circle_ids = Common::GetFriendUid($uid);
            //获取圈子普通动态数据，每次显示10条
            $data_list = DB::table('v_goods_list')->whereIn('uid',$circle_ids)
                ->orderBy('id','desc')->simplePaginate(10);
            $data_list = $data_list->items();
            if(count($data_list)<= 0){
                $retJson->message = "最后一页，没有数据了";
                return $retJson->toJson();
            }
            //获取文件地址
            $items = json_decode(json_encode($data_list),true);
            //去除没有权限的商品
            Common::FilterRelease($items,$uid);
            //添加文件访问地址
            Common::SetFileUrl($items,ReleaseEnum::GOODS);
            $retJson->data['CircleGoods'] = $items;
            return $retJson->toJson();

        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

}