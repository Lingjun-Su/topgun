<?php

// app/Http/Controllers/Api/V1/UserController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // 分页获取非删除状态的用户
        $data = User::paginate($request->get('per_page', 10));

        return $this->success($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:Users',
            'phone' => 'required|digits:11|unique:Users',
            'password' => 'required|min:6',
        ]);
        $data['data_permissions'] = $request->input('data_permissions');
        $user = User::create($data);

        return $this->success($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 如果是admin，只允许修改密码
        if ($user->name === 'admin') {
            $request->validate(['password' => 'required|min:6']);
            $user->update(['password' => $request->password]);
        } else {
            $data = $request->only(['name', 'phone', 'role_id', 'data_permissions']);
            // 如果填写了密码，则更新密码
            if ($request->filled('password')) {
                $request->validate(['password' => 'min:6']);
                $data['password'] = $request->password;
            }
            $user->update($data);
        }

        return $this->success($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        // 核心逻辑：admin不能被删除
        if ($user->name === 'admin' || $user->name === 'Admin') {
            return response()->json(['message' => '管理员账号不可删除'], 403);
        }
        $data = $user->delete(); // Eloquent SoftDeletes 自动处理逻辑删除

        return $this->success($data);
    }

    /**
     * 修改当前登录用户的密码
     */
    public function changePassword(Request $request)
    {
        // 1. 严谨的数据验证
        $request->validate([
            'oldPassword' => 'required',
            'newPassword' => 'required|min:6|confirmed', // 自动校验 newPassword_confirmation
        ], [
            'newPassword.confirmed' => '两次输入的新密码不一致',
            'newPassword.min' => '新密码至少需要6位',
        ]);

        $user = Auth::user(); // 严谨：从 Token 获取用户，而不是传 ID

        // 2. 验证原密码是否正确
        if (! Hash::check($request->oldPassword, $user->password)) {
            return response()->json([
                'message' => '原密码验证失败，请重新输入',
            ], 422);
        }

        // 3. 执行更新并记录审计（如果你用了 Auditing 扩展，这里会自动记录）
        $user->password = $request->newPassword;
        $user->save();

        return $this->success($user, '密码修改成功');
    }
}
