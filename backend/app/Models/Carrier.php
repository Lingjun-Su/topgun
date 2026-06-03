<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

use App\Models\BaseModel;//基础

class Carrier extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * 关联到的表名
     */
    protected $table = 'carrier';

    /**
     * 允许批量赋值的字段
     */
    protected $fillable = [
        'name', 'short_name', 'code', 'contents',
        'contact_person', 'contact_phone', 'address',
        'created_by', 'updated_by', 'deleted_by','status',
    ];

    //强制输出格式
    protected $casts=[
        'status' =>'integer',
        'id'=>'integer',
    ];
    /**
     * 模型引导方法：处理审计逻辑与自动字段填充
     */
    protected static function booted()
    {
        // 创建前：记录创建人
        static::creating(function ($model) {
            $model->created_by = Auth::id();
        });

        // 更新前：记录修改人并写入审计表
        static::updating(function ($model) {
            $model->updated_by = Auth::id();
            static::logAudit($model, 'update');
        });

        // 删除前：记录删除人
        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
            $model->save(); // 强制保存 deleted_by 后再进行逻辑删除
            static::logAudit($model, 'delete');
        });
    }

    /**
     * 手动记录审计日志到 audits 表
     * * @param Model $model
     * @param string $event
     */
    protected static function logAudit($model, $event)
    {
        \DB::table('audits')->insert([
            'user_id'    => Auth::id(),
            'event'      => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'old_values' => json_encode($model->getOriginal()),
            'new_values' => json_encode($model->getDirty()),
            'url'        => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
