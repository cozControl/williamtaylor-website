<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\LimitedEditionController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\SnippePaymentController;
use App\Http\Controllers\StorefrontCollectionController;
use App\Http\Controllers\StorefrontCollectionsController;
use App\Http\Controllers\StorefrontProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomepageController::class)->name('home');
Route::get('checkout/payment-status/{reference}', [SnippePaymentController::class, 'status'])->where('reference', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('checkout.payment-status');
Route::post('webhooks/snippe', [SnippePaymentController::class, 'webhook'])->name('snippe.webhook');
Route::get('checkout/snippe/return/{reference}', [SnippePaymentController::class, 'returned'])->where('reference', '[a-f0-9]{64}')->name('snippe.return');
Route::post('checkout/snippe/retry/{reference}', [SnippePaymentController::class, 'retry'])->where('reference', '[a-f0-9]{64}')->block()->middleware('throttle:10,1')->name('snippe.retry');
Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::get('checkout', [CheckoutController::class, 'show'])->block()->name('checkout.show');
Route::post('checkout', [CheckoutController::class, 'store'])->block()->middleware('throttle:30,1')->name('checkout.store');
Route::get('order-confirmation/{reference}', [CheckoutController::class, 'confirmation'])->where('reference', '[a-f0-9]{64}')->name('checkout.confirmation');
Route::post('cart/items', [CartController::class, 'store'])->block()->name('cart.store');
Route::patch('cart/items/{variant}', [CartController::class, 'update'])->block()->name('cart.update');
Route::delete('cart/items/{variant}', [CartController::class, 'destroy'])->block()->name('cart.destroy');
Route::get('about', AboutController::class)->name('about');

Route::get('collections', StorefrontCollectionsController::class)->name('collections.index');
Route::get('collections/{collection:slug}', StorefrontCollectionController::class)->name('collections.show');
Route::view('shop', 'frontend.shop')->name('products.index');
Route::get('pre-order', PreOrderController::class)->name('preorders.index');
Route::get('limited-edition', LimitedEditionController::class)->name('limited-edition.index');
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
