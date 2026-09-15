<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class CompanyController extends Controller
{
    // 只返回本集团的下属公司
    public static function index()
    {
        // 只查顶级组织，并递归预加载所有下级和银行账户
        $data = Organization::with(['childrenRecursive', 'bankAccounts'])
            ->select('id', 'name', 'short_name')
            ->where('parent_id', 1) // 或者根据你 migration 设定的 null
            ->get();

        return $this->success($data);
    }
}
