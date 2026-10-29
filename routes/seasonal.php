<?php

use App\Http\Controllers\Seasonal\ProductController;
use App\Http\Controllers\Seasonal\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('seasonal/{campaign}')
    ->where(['campaign' => '[a-z0-9-]+'])
    ->name('seasonal.')
    ->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
        Route::post('/confirm', [ReservationController::class, 'confirm'])->name('reservations.confirm');
        Route::post('/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
        Route::post('/reserve', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/complete', [ReservationController::class, 'complete'])->name('reservations.complete');
    });
