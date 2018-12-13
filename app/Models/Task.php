<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/13
 * Time: 14:40
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_task';
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