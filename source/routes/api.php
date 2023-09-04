<?php

use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('users', [UsersController::class, 'index']);
    Route::apiResource('users', UsersController::class);
    Route::apiResource('services', ServicesController::class);
    Route::apiResource('orders', OrdersController::class);
});

Route::post('/login', [AuthController::class, 'auth']);