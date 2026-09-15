<?php

namespace App\Providers;

use App\Models\Contract\MasterContract;
use App\Models\User;
use App\Observers\Contract\MasterContractObserver;
use App\Policies\Contract\MasterContractPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        MasterContract::class => MasterContractPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 事件驱动流水线由 Laravel 自动发现（Listeners 目录下的 handle* / handle 方法）
        MasterContract::observe(MasterContractObserver::class);

        Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^1[3-9]\d{9}$/', $value);
        });

        if (config('app.debug')) {
            DB::listen(function ($query) {
                error_log('SQL => '.$query->sql.' ['.implode(',', $query->bindings).']');
            });
        }

        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;

            if ($user instanceof User) {
                try {
                    DB::transaction(function () use ($user) {
                        DB::table('login_logs')->insert([
                            'user_id' => $user->id,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'login_at' => now(),
                        ]);

                        $user->update([
                            'last_login_at' => now(),
                        ]);
                    });
                } catch (\Exception $e) {
                    \Log::error('登录事件记录失败: '.$e->getMessage());
                }
            }
        });
    }
}
