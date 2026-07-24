<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\PagePreviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('collections', 'frontend.collections')->name('collections.index');
Route::view('shop', 'frontend.shop')->name('products.index');
Route::view('pre-order', 'frontend.pre-order')->name('preorders.index');
Route::view('limited-edition', 'frontend.limited-edition')->name('limited-edition.index');
Route::view('gift-cards', 'frontend.gift-cards')->name('gift-cards.index');
Route::view('wishlist', 'frontend.wishlist')->name('wishlist.index');

Route::prefix('products')->name('products.')->group(function () {
    Route::view('the-taylor-oxford-shirt', 'frontend.products.taylor-oxford-shirt')
        ->name('taylor-oxford-shirt');
    Route::view('mercerized-cotton-polo', 'frontend.products.mercerized-cotton-polo')
        ->name('mercerized-cotton-polo');
    Route::view('the-dar-es-salaam-linen-suit', 'frontend.products.dar-es-salaam-linen-suit')
        ->name('dar-es-salaam-linen-suit');
    Route::view('slim-tapered-chinos', 'frontend.products.slim-tapered-chinos')
        ->name('slim-tapered-chinos');
    Route::view('the-executive-overcoat', 'frontend.products.executive-overcoat')
        ->name('executive-overcoat');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

Route::get('/preview/pages/{page}/{revision}', PagePreviewController::class)
    ->middleware(['auth', 'verified', 'signed', 'can:'.PermissionRegistry::PAGES_PREVIEW])
    ->name('preview.pages.show');
