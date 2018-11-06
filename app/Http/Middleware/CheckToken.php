<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 11:54
 */

namespace App\Http\Middleware;

use App\Lib\ErrorCode;
use App\Lib\ReturnData;
use Closure;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;


class CheckToken extends BaseMiddleware
{

    public function handle($request, Closure $next)
    {
        $ret_data =  ReturnData::createReturn();
        try{
            if(auth()->check()) {
                $user = auth()->user();
                if($user->islogin == 1){
                    $ret_data->code = ErrorCode::NO_LOGIN;
                    $ret_data->message = '禁止登录';
                    return $ret_data->toJson();
                }
                return $next($request);
            }else{
                try {
                    // 刷新用户的 token
                    $token = auth()->refresh();
                    // 使用一次性登录以保证此次请求的成功
                    $uid = auth()->manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray()['sub'];
                    Auth::guard()->onceUsingId($uid);
                    // 在响应头中返回新的 token
                    return $this->setAuthenticationHeader($next($request), $token);
                } catch (JWTException $e) {
                    $ret_data->code = ErrorCode::TOKEN_ERROR;
                    $ret_data->message = 'token已失效,请重新登录';
                    return $ret_data->toJson();
                }
            }
        }catch (\Exception $e){
            $ret_data->code = ErrorCode::EXCEPTION;
            $ret_data->message = $e->getMessage();
            return $ret_data->toJson();
        }
    }

}