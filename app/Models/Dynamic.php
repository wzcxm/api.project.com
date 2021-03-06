<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 10:26
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Dynamic -- 动态发布表
 * @package App\Models
 */
class Dynamic extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_dynamic';
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
     * 获取原创动态
     */
    public function frontDynamic(){
        return $this->hasOne('App\Models\Dynamic','id','init_id');
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