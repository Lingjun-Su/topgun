<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Logout extends Controller
{
    public function logout(Request $request)
    {
        // 删除当前使用的 Token
        $request->user()->currentAccessToken()->delete();
        return $this->success('成功退出登录');
    }
}
