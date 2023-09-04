<?php

use App\Http\Controllers\Api\OrderMessagesController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OrderStatusLogsController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DivisionsController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('users', UsersController::class);
    Route::apiResource('order_messages', OrderMessagesController::class);
    Route::apiResource('order_status_logs', OrderStatusLogsController::class);

});

Route::apiResource('orders', OrdersController::class);
Route::apiResource('divisions', DivisionsController::class);
Route::post('/login', [AuthController::class, 'auth']);