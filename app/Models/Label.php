<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 11:22
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Label --标签设置表
 * @package App\Models
 */
class Label extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_sys_label';
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