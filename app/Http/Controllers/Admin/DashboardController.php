<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Every figure below comes from a live database query; no sample data.
        $stats = [
            'users' => User::query()->count(),
            'active_users' => User::query()->active()->count(),
            'inactive_users' => User::query()->where('is_active', false)->count(),
            'roles' => Role::query()->count(),
            'logins_today' => AuditLog::query()->where('event', 'auth.login')->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        $recentActivity = $user->can('audit.view')
            ? AuditLog::query()->with('user:id,name')->latest('id')->limit(8)->get()
            : collect();

        $recentLogins = $user->can('users.view')
            ? User::query()->whereNotNull('last_login_at')->latest('last_login_at')->limit(5)->get(['id', 'name', 'email', 'last_login_at', 'last_login_ip'])
            : collect();

        return view('admin.dashboard.index', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'recentLogins' => $recentLogins,
            'system' => $this->systemStatus(),
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * @return array<string, array{value: string, ok: bool|null}>
     */
    protected function systemStatus(): array
    {
        try {
            DB::connection()->getPdo();
            $database = DB::connection()->getDriverName().' · '.DB::connection()->getDatabaseName();
            $databaseOk = true;
        } catch (Throwable) {
            $database = __('admin.dashboard.system.unavailable');
            $databaseOk = false;
        }

        $failedJobs = rescue(fn (): int => DB::table('failed_jobs')->count(), 0, false);

        return [
            'environment' => ['value' => app()->environment(), 'ok' => null],
            'debug' => ['value' => config('app.debug') ? __('admin.common.on') : __('admin.common.off'), 'ok' => ! config('app.debug') || app()->isLocal()],
            'laravel' => ['value' => app()->version(), 'ok' => null],
            'php' => ['value' => PHP_VERSION, 'ok' => null],
            'database' => ['value' => $database, 'ok' => $databaseOk],
            'cache' => ['value' => config('cache.default'), 'ok' => null],
            'queue' => ['value' => config('queue.default'), 'ok' => null],
            'failed_jobs' => ['value' => (string) $failedJobs, 'ok' => $failedJobs === 0],
            'storage_link' => ['value' => File::exists(public_path('storage')) ? __('admin.common.yes') : __('admin.common.no'), 'ok' => File::exists(public_path('storage'))],
        ];
    }
}
