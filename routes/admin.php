<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaPickerController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleAccessController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\UserAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'can:'.PermissionRegistry::ADMIN_ACCESS])
    ->group(function (): void {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('/homepage', [HomepageController::class, 'edit'])->middleware('can:'.PermissionRegistry::SETTINGS_VIEW)->name('homepage.edit');
        Route::put('/homepage', [HomepageController::class, 'update'])->middleware('can:'.PermissionRegistry::SETTINGS_MANAGE)->name('homepage.update');
        Route::get('/catalogue', CatalogueController::class)->middleware('can:'.PermissionRegistry::PRODUCTS_VIEW)->name('catalogue.index');
        Route::get('/collections', [CollectionController::class, 'index'])->middleware('can:'.PermissionRegistry::PRODUCTS_VIEW)->name('collections.index');
        Route::get('/collections/create', [CollectionController::class, 'create'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('collections.create');
        Route::post('/collections', [CollectionController::class, 'store'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('collections.store');
        Route::get('/collections/{collection}/edit', [CollectionController::class, 'edit'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('collections.edit');
        Route::put('/collections/{collection}', [CollectionController::class, 'update'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('collections.update');
        Route::get('/content/pages', [ContentPageController::class, 'index'])->middleware('can:'.PermissionRegistry::PAGES_VIEW)->name('content.pages.index');
        Route::get('/content/pages/create', [ContentPageController::class, 'create'])->middleware('can:'.PermissionRegistry::PAGES_CREATE)->name('content.pages.create');
        Route::get('/content/pages/review', [ContentPageController::class, 'review'])->middleware('can:'.PermissionRegistry::PAGES_REVIEW)->name('content.pages.review-queue');
        Route::get('/content/pages/{page}', [ContentPageController::class, 'show'])->middleware('can:'.PermissionRegistry::PAGES_VIEW)->name('content.pages.show');
        Route::get('/content/pages/{page}/edit', [ContentPageController::class, 'edit'])->middleware('can:'.PermissionRegistry::PAGES_EDIT)->name('content.pages.edit');
        Route::get('/content/navigation', [SiteContentController::class, 'navigation'])->middleware('can:'.PermissionRegistry::NAVIGATION_VIEW)->name('content.navigation.index');
        Route::get('/content/navigation/{siteContentResource}', [SiteContentController::class, 'navigationShow'])->middleware('can:'.PermissionRegistry::NAVIGATION_VIEW)->name('content.navigation.show');
        Route::get('/content/navigation/{siteContentResource}/edit', [SiteContentController::class, 'navigationEdit'])->middleware('can:'.PermissionRegistry::NAVIGATION_EDIT)->name('content.navigation.edit');
        Route::get('/content/announcements', [SiteContentController::class, 'announcements'])->middleware('can:'.PermissionRegistry::ANNOUNCEMENTS_VIEW)->name('content.announcements.index');
        Route::get('/content/announcements/create', [SiteContentController::class, 'announcementCreate'])->middleware('can:'.PermissionRegistry::ANNOUNCEMENTS_CREATE)->name('content.announcements.create');
        Route::post('/content/announcements', [SiteContentController::class, 'announcementStore'])->middleware('can:'.PermissionRegistry::ANNOUNCEMENTS_CREATE)->name('content.announcements.store');
        Route::get('/content/announcements/{siteContentResource}', [SiteContentController::class, 'announcementShow'])->middleware('can:'.PermissionRegistry::ANNOUNCEMENTS_VIEW)->name('content.announcements.show');
        Route::get('/content/announcements/{siteContentResource}/edit', [SiteContentController::class, 'announcementEdit'])->middleware('can:'.PermissionRegistry::ANNOUNCEMENTS_EDIT)->name('content.announcements.edit');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.index');
        Route::get('/orders/create', [OrderController::class, 'create'])->middleware('can:'.PermissionRegistry::ORDERS_CREATE)->name('orders.create');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('can:'.PermissionRegistry::ORDERS_CREATE)->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.show');
        Route::post('/orders/{order}/transition', [OrderController::class, 'transition'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.transition');
        Route::post('/orders/{order}/notes', [OrderController::class, 'note'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.notes.store');
        Route::post('/orders/{order}/payment-status', [OrderController::class, 'payment'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.payment');
        Route::get('/orders/{order}/summary', [OrderController::class, 'summary'])->middleware('can:'.PermissionRegistry::ORDERS_VIEW)->name('orders.summary');
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware('can:'.PermissionRegistry::ORDERS_RECEIPTS_VIEW)->name('orders.receipt');
        Route::get('/media', [MediaController::class, 'index'])->middleware('can:'.PermissionRegistry::MEDIA_VIEW)->name('media.index');
        Route::get('/media-picker', MediaPickerController::class)->middleware('can:'.PermissionRegistry::MEDIA_VIEW)->name('media.picker');
        Route::get('/media/{mediaAsset}', [MediaController::class, 'show'])->middleware('can:'.PermissionRegistry::MEDIA_VIEW)->name('media.show');
        Route::get('/products', [ProductController::class, 'index'])->middleware('can:'.PermissionRegistry::PRODUCTS_VIEW)->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('can:'.PermissionRegistry::PRODUCTS_VIEW)->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('products.update');
        Route::get('/product-categories', [ProductCategoryController::class, 'index'])->middleware('can:'.PermissionRegistry::PRODUCTS_VIEW)->name('product-categories.index');
        Route::get('/product-categories/create', [ProductCategoryController::class, 'create'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('product-categories.create');
        Route::post('/product-categories', [ProductCategoryController::class, 'store'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('product-categories.store');
        Route::get('/product-categories/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('product-categories.edit');
        Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('product-categories.update');
        Route::patch('/product-categories/{productCategory}/archive', [ProductCategoryController::class, 'archive'])->middleware('can:'.PermissionRegistry::PRODUCTS_MANAGE)->name('product-categories.archive');
        Route::get('/access/users', [UserAccessController::class, 'index'])
            ->middleware('can:'.PermissionRegistry::USERS_VIEW)
            ->name('access.users.index');
        Route::get('/access/users/{user}', [UserAccessController::class, 'show'])
            ->middleware('can:'.PermissionRegistry::USERS_VIEW)
            ->name('access.users.show');

        Route::get('/access/roles', [RoleAccessController::class, 'index'])
            ->middleware('can:'.PermissionRegistry::ROLES_VIEW)
            ->name('access.roles.index');
        Route::get('/access/roles/{role}', [RoleAccessController::class, 'show'])
            ->middleware('can:'.PermissionRegistry::ROLES_VIEW)
            ->name('access.roles.show');
        Route::view('/audit', 'admin.placeholders.audit')->middleware('can:'.PermissionRegistry::AUDIT_VIEW)->name('audit.index');
        Route::get('/settings', [SiteContentController::class, 'settings'])->middleware('can:'.PermissionRegistry::SETTINGS_VIEW)->name('settings.index');
    });

Route::get('/preview/site-content/{siteContentResource}/{revision}', [SiteContentController::class, 'preview'])
    ->middleware(['auth', 'verified', 'can:'.PermissionRegistry::ADMIN_ACCESS, 'signed'])
    ->name('preview.site-content.show');
Route::get('/preview/site-content/{siteContentResource}/{revision}/render', [SiteContentController::class, 'renderPreview'])
    ->middleware(['auth', 'verified', 'can:'.PermissionRegistry::ADMIN_ACCESS, 'signed'])
    ->name('preview.site-content.render');
