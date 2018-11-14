<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 10:27
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Goods -- 付费商品发布表
 * @package App\Models
 */
class Goods extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_goods';
    /**
     * 主键
     */
    protected $primaryKey = 'id';
    /**
     * 执行模型是否自动维护时间戳.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 可见用户转化为json格式保存
     * @var array
     */
    protected $casts = [
        'visible_uids' => 'array',
    ];

    /**
     * 获取原创商品
     */
    public function firstGoods(){
        return $this->hasOne('App\Models\Goods','id','first_id');
    }

    /**
     * 发布人信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function userInfo(){
        return $this->hasOne('App\Models\Users','uid','uid');
    }

    /**
     * label名称
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function labelInfo(){
        return $this->hasOne('App\Models\Label','id','label');
    }
}