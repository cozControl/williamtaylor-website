<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\RoleAccessController;
use App\Http\Controllers\Admin\UserAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'can:'.PermissionRegistry::ADMIN_ACCESS])
    ->group(function (): void {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('/content/pages', [ContentPageController::class, 'index'])->middleware('can:'.PermissionRegistry::PAGES_VIEW)->name('content.pages.index');
        Route::get('/content/pages/create', [ContentPageController::class, 'create'])->middleware('can:'.PermissionRegistry::PAGES_CREATE)->name('content.pages.create');
        Route::get('/content/pages/{page}', [ContentPageController::class, 'show'])->middleware('can:'.PermissionRegistry::PAGES_VIEW)->name('content.pages.show');
        Route::get('/content/pages/{page}/edit', [ContentPageController::class, 'edit'])->middleware('can:'.PermissionRegistry::PAGES_EDIT)->name('content.pages.edit');
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
        Route::view('/settings', 'admin.placeholders.settings')->middleware('can:'.PermissionRegistry::SETTINGS_VIEW)->name('settings.index');
    });
