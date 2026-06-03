<?php
namespace App\Traits;

use App\Models\Task;
use App\Models\ApprovalLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasApproval
{
    /**
     * 获取当前正在进行的任务 (通常一个实体同一时间只有一个活动任务)
     */
    public function currentTask(): MorphOne
    {
        return $this->morphOne(Task::class, 'taskable')->where('status', '!=', 'done');
    }

    /**
     * 获取所有任务历史
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /**
     * 获取审批轨迹
     */
    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'logable')->latest();
    }
}
