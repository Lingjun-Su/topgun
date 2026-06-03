<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

use App\Models\User; // 必须引入

use App\Models\Contract\MasterContract;//框架合同
use App\Policies\Contract\MasterContractPolicy;//
use App\Observers\Contract\MasterContractObserver;//

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        MasterContract::class => MasterContractPolicy::class,//合同
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //框架合同审批流程记录
        MasterContract::observe(MasterContractObserver::class);

        // 1. 验证手机号格式 (严谨的正则)
        Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^1[3-9]\d{9}$/', $value);
        });


        DB::listen(function ($query) {
            // 这种写法会直接把 SQL 打印在 php artisan serve 的窗口里
            error_log("SQL => " . $query->sql . " [" . implode(',', $query->bindings) . "]");
        });

        // 2. 登录事件监听
        Event::listen(Login::class, function (Login $event) {
            // 获取用户实例
            $user = $event->user;

            // --- 严谨性检查：确保 $user 是真正的 Eloquent 模型 ---
            if ($user instanceof User) {
                try {
                    // 使用数据库事务保证日志与用户状态更新的一致性
                    DB::transaction(function () use ($user) {
                        // 记录登录日志
                        DB::table('login_logs')->insert([
                            'user_id'    => $user->id,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'login_at'   => now(),
                        ]);

                        // 更新用户最后登录时间
                        // 注意：这里使用 quietUpdate 或直接 update
                        // 如果使用了审计插件，update 会自动记录变动到 audits 表
                        $user->update([
                            'last_login_at' => now(),
                        ]);
                    });
                } catch (\Exception $e) {
                    // 记录错误日志，防止登录过程因日志写入失败而中断
                    \Log::error("登录事件记录失败: " . $e->getMessage());
                }
            }
        });
    }
}
