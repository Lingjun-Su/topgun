<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Models\CallbackLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackLogController extends Controller
{
    /**
     * 回调日志列表（分页）
     * GET /api/v2/callback-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = CallbackLog::orderBy('id', 'desc');

        if ($request->filled('channel_pid')) {
            $query->where('channel_pid', 'like', '%' . $request->input('channel_pid') . '%');
        }

        if ($request->filled('callback_type')) {
            $query->where('callback_type', $request->input('callback_type'));
        }

        if ($request->filled('status') && $request->input('status') !== '') {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('date_start')) {
            $query->where('created_at', '>=', $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->where('created_at', '<=', $request->input('date_end') . ' 23:59:59');
        }

        if ($request->filled('forward_order_id')) {
            $query->where('forward_order_id', $request->input('forward_order_id'));
        }

        $data = $query->paginate($request->get('per_page', 15));

        return $this->success($data);
    }

    /**
     * 回调日志详情
     * GET /api/v2/callback-logs/{id}
     */
    public function show($id): JsonResponse
    {
        $log = CallbackLog::find($id);

        if (! $log) {
            return $this->error('回调日志不存在', 404);
        }

        return $this->success($log);
    }
}