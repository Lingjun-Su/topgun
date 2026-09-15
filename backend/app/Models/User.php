<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, /* HasRoles, */ SoftDeletes;

    /**
     * 严谨规范：SQL Server 2019 建议表名大小写与数据库严格一致
     */
    protected $table = 'Users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'last_login_at',
        'employee_id', // 员工
        'role_id', // 角色
        'data_permissions', // 数据权限
    ];

    /**
     * 【关键修正】审计排除字段
     * 严禁审计 last_login_at，因为它在登录事件中更新，
     * 此时审计系统正在解析用户信息，极易造成无限递归。
     */
    protected $auditExclude = [
        'password',
        'remember_token',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime', // 建议显式转换
            'data_permissions' => 'array', // JSON 自动解码为数组
        ];
    }

    // --- 关系映射 (Eloquent Relations) ---

    /**
     * 一对一关联员工表
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 多对一关联角色表
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // --- 核心：自动化审计日志追踪 (Data Audit) ---
    protected static function booted()
    {
        // 1. 拦截“创建”操作
        static::created(function ($model) {
            self::logAudit('CREATE', $model, null, $model->getAttributes());
        });

        // 2. 拦截“更新”操作（包含逻辑删除触发的 deleted_at 变动）
        static::updated(function ($model) {
            // 获取发生变动的字段
            $changes = $model->getChanges();
            $oldValues = array_intersect_key($model->getOriginal(), $changes);

            $action = isset($changes['deleted_at']) && ! is_null($changes['deleted_at']) ? 'SOFT_DELETE' : 'UPDATE';

            self::logAudit($action, $model, $oldValues, $changes);
        });
    }

    /**
     * 写入审计表核心公用方法
     */
    private static function logAudit(string $action, $model, ?array $old, array $new): void
    {
        // 严格遵循 audits 规范表结构设计 [cite: 1]
        DB::table('audits')->insert([
            'user_id' => Auth::id() ?? 0, // 操作人ID（0代表系统初始或未登录） [cite: 1]
            'ip_address' => request()->ip() ?? '127.0.0.1', // [cite:1]
            'new_values' => json_encode([
                'table' => $model->getTable(),
                'id' => $model->id,
                'action' => $action,
                'before' => $old,
                'after' => $new,
            ], JSON_UNESCAPED_UNICODE), // [cite: 1]
            'auditable_type' => 'App\Models\Users', // 默认业务拦截状态码 [cite: 1]
            'auditable_id' => $model->id,
            'event' => $action, // [cite: 1]
            'created_at' => now(),
        ]);
    }
}
