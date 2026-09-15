<?php

// app/Observers/MasterContractObserver.php

namespace App\Observers\Contract;

use App\Models\Contract\MasterContract;
use App\Models\Contract\MasterContractLog;
use Illuminate\Support\Facades\Auth;

class MasterContractObserver
{
    public function updated(MasterContract $contract)
    {
        if ($contract->isDirty('status')) {
            $oldStatus = $contract->getOriginal('status');
            $newStatus = $contract->status;

            $action = $this->guessAction($oldStatus, $newStatus);

            MasterContractLog::create([
                'master_id' => $contract->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'action_type' => $action,
                'remark' => request()->input('remark'),
                'user_id' => Auth::id() ?? 0, // 当前用户ID
            ]);
        }
    }

    protected function guessAction($oldStatus, $newStatus): string
    {
        $map = [
            MasterContract::STATUS_DRAFT.'_'.MasterContract::STATUS_PENDING => 'submit',
            MasterContract::STATUS_PENDING.'_'.MasterContract::STATUS_ACTIVE => 'approve',
            MasterContract::STATUS_PENDING.'_'.MasterContract::STATUS_REJECTED => 'reject',
            MasterContract::STATUS_PENDING.'_'.MasterContract::STATUS_DRAFT => 'withdraw',
            MasterContract::STATUS_REJECTED.'_'.MasterContract::STATUS_PENDING => 'submit',
            MasterContract::STATUS_DRAFT.'_'.MasterContract::STATUS_VOID => 'void',
            MasterContract::STATUS_REJECTED.'_'.MasterContract::STATUS_VOID => 'void',
            MasterContract::STATUS_ACTIVE.'_'.MasterContract::STATUS_TERMINATED => 'terminate',
            MasterContract::STATUS_ACTIVE.'_'.MasterContract::STATUS_EXPIRED => 'expire',
        ];
        $key = $oldStatus.'_'.$newStatus;

        return $map[$key] ?? 'unknown';
    }
}
