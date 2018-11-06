<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 11:20
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class CircleCheck --圈子审核设置表
 * @package App\Models
 */
class CircleCheck extends  Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_funds';
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
     * 部分审核用户，转化为json格式保存
     * @var array
     */
    protected $casts = [
        'check_users' => 'array',
    ];
}