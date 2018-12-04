<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 9:28
 */

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
/**
 * Class Users -用户表
 * @package App\Models
 */
class Users extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable;
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'pro_mall_users';
    /**
     * 主键
     */
    protected $primaryKey = 'uid';
    /**
     * 执行模型是否自动维护时间戳.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'pwd',
        'pay_pwd',
        'access',
        'is_online',
        'last_ip',
        'gender',
        'bg_img',
        'isdelete',
        'create_time',
        'update_time',
        'file_key'
    ];
    /**
     * 用户钱包
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function wallet(){
        return $this->hasOne('App\Models\Wallet','uid');
    }
    /**
     * 用户好友
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
//    public function friends(){
//        return $this->hasOne('App\Models\Friend','uid');
//    }
}