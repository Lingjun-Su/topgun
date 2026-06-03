<?php

namespace App\Http\Controllers\Contract;

use App\Models\contract\MasterContractLog;
use App\Models\contract\MasterContract;
use App\Http\Requests\contract\LogStoreRequest;
use App\Traits\ApiResponse; // 引入统一响应 Trait
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MasterContractLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $logs = MasterContractLog::paginate($request->per_page ?? 15);

        return $this->success($logs); // 返回 200 成功响应
    }

    /**
     * 存储审核日志并更新合同状态
     * * @param LogStoreRequest $request
     */
    public function store(LogStoreRequest $request)
    {
        $validated = $request->validated();

        // 获取当前用户ID (从 Token 解析)
        $operatorId = Auth::id() ?? 1; // 演示默认为1


        try {

             // 1. 查找主合同状态
            $contract = MasterContract::findOrFail($validated['master_id']);

            $validated['from_status'] =$contract->status;//原来的状态
            $validated['to_status'] =$validated['action_type'];//现在的状态

            // 1. 插入审核日志
            $log = MasterContractLog::create(array_merge($validated, [
                'user_id' => $operatorId
            ]));

            //审批动作：'动作: 0-提交, 1-通过, 2-驳回, 3-终止, 4-作废'
            //对应合同状态状态: 0草稿 1审批中 2驳回 3已生效 4已过期 5已终止 6作废
            $status =$validated['action_type']==1?3:2;

            $contract->update([
                'status' => $status,
            ]);

            return $this->success($log, '审核记录已提交');

        } catch (\Exception $e) {

            // 异常审计记录
            DB::table('audits')->insert([
                'user_id'          => $operatorId,
                'ip_address'       => $request->ip(),
                'request_payload'  => json_encode($validated),
                'response_code'    => 500,
                'response_message' => $e->getMessage(),
                'created_at'       => now(),
            ]);

            return $this->error('系统繁忙，请稍后再试: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取指定合同的流转日志
     */
    public function showByMaster($masterId)
    {
        $logs = MasterContractLog::with('operator:id,name')
            ->where('master_id', $masterId)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($logs);
    }

    // 从框架合同创建执行合同（分割）
    public function createExecutionContract(Request $request, $masterId)
    {
        $master = MasterContract::findOrFail($masterId);

        $validated = $request->validate([
            'external_no' => 'required|string|max:50|unique:execution_contracts,external_no',
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:REVENUE,COST',
            'total_amount'=> 'required|numeric|min:0',
            'ratio'       => 'nullable|numeric|min:0|max:100',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
            'region'      => 'nullable|string|max:50',
            'content'     => 'nullable|string',
            'project_id'  => 'nullable|exists:projects,id',
            'parent_id'   => 'nullable|exists:execution_contracts,id',
        ]);

        // 生成系统唯一编号：可基于框架合同编号 + 时间戳
        $systemNo = $master->contract_no . '-' . date('YmdHis') . rand(100,999);
        while (ExecutionContract::where('system_no', $systemNo)->exists()) {
            $systemNo = $master->contract_no . '-' . date('YmdHis') . rand(100,999);
        }

        $execution = ExecutionContract::create([
            'master_id'    => $master->id,
            'org_a_id'     => $master->org_a_id,
            'org_b_id'     => $master->org_b_id,
            'system_no'    => $systemNo,
            'status'       => 0, // 草稿
            'project_id'   => $validated['project_id'] ?? null,
            'parent_id'    => $validated['parent_id'] ?? null,
            'external_no'  => $validated['external_no'],
            'title'        => $validated['title'],
            'content'      => $validated['content'] ?? null,
            'type'         => $validated['type'],
            'region'       => $validated['region'] ?? null,
            'total_amount' => $validated['total_amount'],
            'ratio'        => $validated['ratio'] ?? 0,
            'tax_rate'     => $validated['tax_rate'] ?? 0,
        ]);

        return response()->json($execution, 201);
    }
}
