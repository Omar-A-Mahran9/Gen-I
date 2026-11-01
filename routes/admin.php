<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Business\BranchController;
use App\Http\Controllers\Admin\Business\BrandController;
use App\Http\Controllers\Admin\Business\CategoryController;
use App\Http\Controllers\Admin\Business\CompanyController;
use App\Http\Controllers\Admin\Business\InventoryController;
use App\Http\Controllers\Admin\Business\OrganizationController;
use App\Http\Controllers\Admin\Business\PolicyVersionController;
use App\Http\Controllers\Admin\Business\PriceListController;
use App\Http\Controllers\Admin\Business\ProductController;
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

    // Module 1 — Organizations & companies.
    Route::patch('organizations/{organization}/status', [OrganizationController::class, 'status'])->name('organizations.status');
    Route::resource('organizations', OrganizationController::class);

    Route::patch('companies/{company}/status', [CompanyController::class, 'status'])->name('companies.status');
    Route::post('companies/{company}/members', [CompanyController::class, 'storeMember'])->name('companies.members.store');
    Route::put('companies/{company}/members/{member}', [CompanyController::class, 'updateMember'])->name('companies.members.update');
    Route::delete('companies/{company}/members/{member}', [CompanyController::class, 'destroyMember'])->name('companies.members.destroy');
    Route::post('companies/{company}/addresses', [CompanyController::class, 'storeAddress'])->name('companies.addresses.store');
    Route::put('companies/{company}/addresses/{address}', [CompanyController::class, 'updateAddress'])->name('companies.addresses.update');
    Route::delete('companies/{company}/addresses/{address}', [CompanyController::class, 'destroyAddress'])->name('companies.addresses.destroy');
    Route::resource('companies', CompanyController::class);

    // Module 2 — Branches (stores and service branches).
    Route::patch('branches/{branch}/status', [BranchController::class, 'status'])->name('branches.status');
    Route::resource('branches', BranchController::class);

    // Module 3 — Product catalog.
    Route::patch('categories/{category}/status', [CategoryController::class, 'status'])->name('categories.status');
    Route::post('categories/{category}/attributes', [CategoryController::class, 'storeAttribute'])->name('categories.attributes.store');
    Route::put('categories/{category}/attributes/{attribute}', [CategoryController::class, 'updateAttribute'])->name('categories.attributes.update');
    Route::delete('categories/{category}/attributes/{attribute}', [CategoryController::class, 'destroyAttribute'])->name('categories.attributes.destroy');
    Route::resource('categories', CategoryController::class);

    Route::patch('brands/{brand}/status', [BrandController::class, 'status'])->name('brands.status');
    Route::resource('brands', BrandController::class);

    Route::patch('products/{product}/status', [ProductController::class, 'status'])->name('products.status');
    Route::post('products/{product}/variants', [ProductController::class, 'storeVariant'])->name('products.variants.store');
    Route::post('products/{product}/media', [ProductController::class, 'storeMedia'])->middleware('throttle:30,1')->name('products.media.store');
    Route::put('products/{product}/media/{media}', [ProductController::class, 'updateMedia'])->name('products.media.update');
    Route::delete('products/{product}/media/{media}', [ProductController::class, 'destroyMedia'])->name('products.media.destroy');
    Route::get('products/{product}/media/{media}/preview', [ProductController::class, 'previewMedia'])->name('products.media.preview');
    Route::resource('products', ProductController::class);
    Route::put('variants/{variant}', [ProductController::class, 'updateVariant'])->name('variants.update');
    Route::patch('variants/{variant}/status', [ProductController::class, 'variantStatus'])->name('variants.status');
    Route::put('variants/{variant}/specifications', [ProductController::class, 'syncSpecifications'])->name('variants.specifications');
    Route::delete('variants/{variant}', [ProductController::class, 'destroyVariant'])->name('variants.destroy');

    // Module 4 — Policies, pricing and inventory.
    Route::post('policies/{policy}/approve', [PolicyVersionController::class, 'approve'])->name('policies.approve');
    Route::post('policies/{policy}/retire', [PolicyVersionController::class, 'retire'])->name('policies.retire');
    Route::resource('policies', PolicyVersionController::class);

    Route::patch('price-lists/{price_list}/status', [PriceListController::class, 'status'])->name('price-lists.status');
    Route::post('price-lists/{price_list}/prices', [PriceListController::class, 'storePrice'])->name('price-lists.prices.store');
    Route::resource('price-lists', PriceListController::class);
    Route::put('prices/{price}', [PriceListController::class, 'updatePrice'])->name('prices.update');
    Route::delete('prices/{price}', [PriceListController::class, 'destroyPrice'])->name('prices.destroy');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('inventory/adjustments', [InventoryController::class, 'adjust'])->middleware('throttle:60,1')->name('inventory.adjust');
});
