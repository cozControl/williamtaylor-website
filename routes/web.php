<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\StorefrontCollectionController;
use App\Http\Controllers\StorefrontProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomepageController::class)->name('home');
Route::get('about', AboutController::class)->name('about');

Route::view('collections', 'frontend.collections')->name('collections.index');
Route::get('collections/{collection:slug}', StorefrontCollectionController::class)->name('collections.show');
Route::view('shop', 'frontend.shop')->name('products.index');
Route::view('pre-order', 'frontend.pre-order')->name('preorders.index');
Route::view('limited-edition', 'frontend.limited-edition')->name('limited-edition.index');
Route::view('gift-cards', 'frontend.gift-cards')->name('gift-cards.index');
Route::view('wishlist', 'frontend.wishlist')->name('wishlist.index');

Route::prefix('products')->name('products.')->group(function () {
    Route::get('the-taylor-oxford-shirt', StorefrontProductController::class)
        ->defaults('product', 'the-taylor-oxford-shirt')
        ->name('taylor-oxford-shirt');
    Route::get('mercerized-cotton-polo', StorefrontProductController::class)->defaults('product', 'mercerized-cotton-polo')
        ->name('mercerized-cotton-polo');
    Route::get('the-dar-es-salaam-linen-suit', StorefrontProductController::class)->defaults('product', 'the-dar-es-salaam-linen-suit')
        ->name('dar-es-salaam-linen-suit');
    Route::get('slim-tapered-chinos', StorefrontProductController::class)->defaults('product', 'slim-tapered-chinos')
        ->name('slim-tapered-chinos');
    Route::get('the-executive-overcoat', StorefrontProductController::class)->defaults('product', 'the-executive-overcoat')
        ->name('executive-overcoat');
    Route::get('{product:slug}', [StorefrontProductController::class, 'show'])->where('product', '[a-z0-9-]+')->name('show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

Route::get('/preview/pages/{page}/{revision}', PagePreviewController::class)
    ->middleware(['auth', 'verified', 'signed', 'can:'.PermissionRegistry::PAGES_PREVIEW])
    ->name('preview.pages.show');
