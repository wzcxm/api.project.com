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
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
class CheckToken extends BaseMiddleware
{

    public function handle($request, Closure $next)
    {
        $retJson =  new ReturnData();
        try{
            if(auth()->check()) {
                $user = auth()->user();
                if($user->islogin == 1){
                    $retJson->code = ErrorCode::NO_LOGIN;
                    $retJson->message = '禁止登录';
                    return $retJson->toJson();
                }
                return $next($request);
            }else{
                try {
                    // 刷新用户的 token
                    $token = auth()->refresh();
                    // 给当前的请求设置性的token,以备在本次请求中需要调用用户信息
                    $request->headers->set('Authorization','Bearer '.$token);
                    // 在响应头中返回新的 token
                    return $this->setAuthenticationHeader($next($request), $token);
                } catch (JWTException $e) {
                    Log::error($e->getMessage());
                    $retJson->code = ErrorCode::TOKEN_ERROR;
                    $retJson->message = 'token已失效,请重新登录';
                    return $retJson->toJson();
                }
            }
        }catch (\Exception $e){
            $retJson->code = ErrorCode::EXCEPTION;
            $retJson->message = $e->getMessage();
            return $retJson->toJson();
        }
    }

}