<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/19
 * Time: 9:55
 */

namespace App\Lib;

/**
 * api返回数据
 * Class ReturnData
 * @package App\Lib
 */
class ReturnData
{
    public $code = 0;
    public $message='success';
    public $data=[];

    public static function createReturn(){
        return new ReturnData();
    }

    public  function toJson(){
        $ret_data = [
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data
        ];
        return urldecode(json_encode($ret_data));
    }
}
