<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin routes. Registered in bootstrap/app.php with the "web" middleware,
| the /admin prefix and the "admin." route-name prefix.
*/

// Language switch: available to guests (login page) and signed-in users.
Route::get('locale/{locale}', LocaleController::class)
    ->where('locale', '[a-z]{2}')
    ->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware(['auth', 'auth.session', 'admin.access'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Profile (own account only).
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications (own notifications only).
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification')
        ->name('notifications.read');

    // Users.
    Route::get('users/search', [UserController::class, 'search'])->name('users.search');
    Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::resource('users', UserController::class);

    // Roles and permissions.
    Route::resource('roles', RoleController::class);

    // Settings.
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    // Audit log.
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
