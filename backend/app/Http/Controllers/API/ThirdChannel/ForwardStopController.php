<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Models\ForwardStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForwardStopController extends Controller
{
    /**
     * 停止记录查询（分页）
     * GET /api/v1/forward-stop-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = ForwardStop::orderBy('id', 'desc');

        if ($request->filled('channel_pid')) {
            $query->where('source_pid', 'like', '%' . $request->input('channel_pid') . '%');
        }

        if ($request->filled('mobile')) {
            $query->where('mobile', 'like', '%' . $request->input('mobile') . '%');
        }

        if ($request->filled('source_order_no')) {
            $query->where('source_order_no', $request->input('source_order_no'));
        }

        if ($request->filled('step')) {
            $query->where('step', $request->input('step'));
        }

        if ($request->filled('condition_type')) {
            $query->where('condition_type', $request->input('condition_type'));
        }

        if ($request->filled('date_start')) {
            $query->where('created_at', '>=', $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->where('created_at', '<=', $request->input('date_end') . ' 23:59:59');
        }

        $data = $query->paginate($request->get('per_page', 15));

        return $this->success($data);
    }

    /**
     * 停止记录详情
     * GET /api/v1/forward-stop-logs/{id}
     */
    public function show($id): JsonResponse
    {
        $stop = ForwardStop::find($id);

        if (! $stop) {
            return $this->error('停止记录不存在', 404);
        }

        return $this->success($stop);
    }
}