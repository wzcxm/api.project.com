<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 11:24
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Files --附件表
 * @package App\Models
 */
class Files extends  Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_files';
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
     * 可以被批量赋值的属性。
     *
     * @var array
     */
    //protected $fillable = ['release_type','release_id','fileurl'];
}