<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\RoleAccessController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\UserAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'can:'.PermissionRegistry::ADMIN_ACCESS])
    ->group(function (): void {
        Route::view('/', 'admin.dashboard')->name('dashboard');
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
        Route::get('/media', [MediaController::class, 'index'])->middleware('can:'.PermissionRegistry::MEDIA_VIEW)->name('media.index');
        Route::get('/media/{mediaAsset}', [MediaController::class, 'show'])->middleware('can:'.PermissionRegistry::MEDIA_VIEW)->name('media.show');
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
