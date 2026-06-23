<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;


// AUTH


Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});


// PRODUCTS + ORDERS (Public)

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);


Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

});

Route::middleware('auth:sanctum')->group(function () {

    // CART - Global Rate Limiter + Per-User API limit
    Route::middleware(['throttle:global', 'throttle:api'])->group(function () {
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);
    });

    // ORDERS - Orders specific limiter (200 global/min + 20 per user/min)
    Route::middleware('throttle:orders')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders', [OrderController::class, 'createFromCart']);
        
        Route::post('/orders/race-condition', [OrderController::class, 'createWithRaceCondition']);
        Route::post('/orders/optimistic', [OrderController::class, 'createWithOptimisticLock']);
        Route::post('/orders/pessimistic', [OrderController::class, 'createWithPessimisticLock']);
        Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

    // CHECKOUT - Strict checkout funnel (30 global/min + 5 per user/min)
    Route::post('orders/checkout', [OrderController::class, 'checkout'])
         ->middleware('throttle:checkout');
});

// SERVER INFO
Route::get('/server', function () {

    return response()->json([
        'port' => request()->server('SERVER_PORT'),
        'pid'  => getmypid(),
    ]);

});