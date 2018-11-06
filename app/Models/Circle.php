<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 10:31
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Circle --圈子合集表（包含动态、付费商品、积分商品、悬赏任务）
 * @package App\Models
 */
class Circle extends  Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_circle';
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
}