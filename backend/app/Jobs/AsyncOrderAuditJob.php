<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AsyncOrderAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auditData;
    protected $statusData;

    public function __construct(array $auditData, array $statusData = [])
    {
        $this->auditData = $auditData;
        $this->statusData = $statusData;
    }

    public function handle()
    {
        // 异步写入审计表和状态流水表，不影响主 API 响应
        DB::transaction(function () {
            if (!empty($this->auditData)) {
                DB::table('audits')->insert($this->auditData);
            }
            if (!empty($this->statusData)) {
                $table = $this->statusData['table'];
                unset($this->statusData['table']);
                DB::table($table)->insert($this->statusData);
            }
        });
    }
}
