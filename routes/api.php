<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CatalogImportController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PolicyVersionController;
use App\Http\Controllers\Api\V1\PriceListController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
| Versioned API. Registered in bootstrap/app.php with the "api" middleware
| and the /api/v1 prefix. Conventions: docs/api-contracts.md.
*/

Route::name('api.v1.')->group(function (): void {
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('user', CurrentUserController::class)->name('user');
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');

        // Module 1 — Organizations & companies.
        Route::patch('organizations/{organization}/status', [OrganizationController::class, 'status'])->name('organizations.status');
        Route::apiResource('organizations', OrganizationController::class);

        Route::patch('companies/{company}/status', [CompanyController::class, 'status'])->name('companies.status');
        Route::get('companies/{company}/members', [CompanyController::class, 'members'])->name('companies.members.index');
        Route::post('companies/{company}/members', [CompanyController::class, 'storeMember'])->name('companies.members.store');
        Route::put('companies/{company}/members/{member}', [CompanyController::class, 'updateMember'])->name('companies.members.update');
        Route::delete('companies/{company}/members/{member}', [CompanyController::class, 'destroyMember'])->name('companies.members.destroy');
        Route::get('companies/{company}/addresses', [CompanyController::class, 'addresses'])->name('companies.addresses.index');
        Route::post('companies/{company}/addresses', [CompanyController::class, 'storeAddress'])->name('companies.addresses.store');
        Route::put('companies/{company}/addresses/{address}', [CompanyController::class, 'updateAddress'])->name('companies.addresses.update');
        Route::delete('companies/{company}/addresses/{address}', [CompanyController::class, 'destroyAddress'])->name('companies.addresses.destroy');
        Route::apiResource('companies', CompanyController::class);

        // Module 2 — Branches (stores and service branches).
        Route::patch('branches/{branch}/status', [BranchController::class, 'status'])->name('branches.status');
        Route::get('branches/{branch}/inventory', [BranchController::class, 'inventory'])->name('branches.inventory');
        Route::apiResource('branches', BranchController::class);

        // Module 3 — Product catalog.
        Route::patch('categories/{category}/status', [CategoryController::class, 'status'])->name('categories.status');
        Route::post('categories/{category}/attributes', [CategoryController::class, 'storeAttribute'])->name('categories.attributes.store');
        Route::put('categories/{category}/attributes/{attribute}', [CategoryController::class, 'updateAttribute'])->name('categories.attributes.update');
        Route::delete('categories/{category}/attributes/{attribute}', [CategoryController::class, 'destroyAttribute'])->name('categories.attributes.destroy');
        Route::apiResource('categories', CategoryController::class);

        Route::patch('brands/{brand}/status', [BrandController::class, 'status'])->name('brands.status');
        Route::apiResource('brands', BrandController::class);

        Route::post('catalog/imports', [CatalogImportController::class, 'store'])->middleware('throttle:10,1')->name('catalog.imports.store');
        Route::get('catalog/imports/template', [CatalogImportController::class, 'template'])->name('catalog.imports.template');

        Route::patch('products/{product}/status', [ProductController::class, 'status'])->name('products.status');
        Route::get('products/{product}/variants', [ProductController::class, 'variants'])->name('products.variants.index');
        Route::post('products/{product}/variants', [ProductController::class, 'storeVariant'])->name('products.variants.store');
        Route::post('products/{product}/media', [ProductController::class, 'storeMedia'])->middleware('throttle:30,1')->name('products.media.store');
        Route::put('products/{product}/media/{media}', [ProductController::class, 'updateMedia'])->name('products.media.update');
        Route::delete('products/{product}/media/{media}', [ProductController::class, 'destroyMedia'])->name('products.media.destroy');
        Route::get('products/{product}/media/{media}/download', [ProductController::class, 'downloadMedia'])->name('products.media.download');
        Route::apiResource('products', ProductController::class);

        Route::put('variants/{variant}', [ProductController::class, 'updateVariant'])->name('variants.update');
        Route::patch('variants/{variant}/status', [ProductController::class, 'variantStatus'])->name('variants.status');
        Route::put('variants/{variant}/specifications', [ProductController::class, 'syncSpecifications'])->name('variants.specifications');
        Route::delete('variants/{variant}', [ProductController::class, 'destroyVariant'])->name('variants.destroy');

        // Module 4 — Policies, pricing and inventory.
        Route::post('policies/{policy}/approve', [PolicyVersionController::class, 'approve'])->name('policies.approve');
        Route::post('policies/{policy}/retire', [PolicyVersionController::class, 'retire'])->name('policies.retire');
        Route::apiResource('policies', PolicyVersionController::class);

        Route::patch('price-lists/{price_list}/status', [PriceListController::class, 'status'])->name('price-lists.status');
        Route::get('price-lists/{price_list}/prices', [PriceListController::class, 'prices'])->name('price-lists.prices.index');
        Route::post('price-lists/{price_list}/prices', [PriceListController::class, 'storePrice'])->name('price-lists.prices.store');
        Route::apiResource('price-lists', PriceListController::class);
        Route::put('prices/{price}', [PriceListController::class, 'updatePrice'])->name('prices.update');
        Route::delete('prices/{price}', [PriceListController::class, 'destroyPrice'])->name('prices.destroy');

        Route::get('inventory', [InventoryController::class, 'balances'])->name('inventory.index');
        Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjustments', [InventoryController::class, 'adjust'])->middleware('throttle:60,1')->name('inventory.adjust');
    });
});
