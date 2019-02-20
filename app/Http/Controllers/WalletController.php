<?php
/**
 * Created by PhpStorm.
 * User: YM
 * Date: 2019/2/19
 * Time: 17:27
 */

namespace App\Http\Controllers;

use App\Lib\ErrorCode;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Lib\ReturnData;
use Illuminate\Support\Facades\DB;

class WalletController  extends  Controller
{
    use ReturnData;

    /**
     * 获取用户钱包信息
     * @param Request $request
     * @return string
     */
    public function GetUserWallet(Request $request){
        try{
            $uid =  auth()->id();//$request->input('uid','');
            $wallet = Wallet::firstOrCreate(['uid'=>$uid]);
            $this->data['Wallet'] = $wallet;
            return $this->toJson();
        }catch (\Exception $e){
            $this->code = ErrorCode::EXCEPTION;
            $this->message = $e->getMessage();
            return $this->toJson();
        }
    }
}