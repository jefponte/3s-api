<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DivisionsController;
use App\Http\Controllers\Api\OrderMessagesController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OrderStatusLogsController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('user.logout');
    Route::apiResource('orders', OrdersController::class)->names('orders.api');
    Route::get('/notifications', [OrdersController::class, 'notifications'])->name('orders.notifications');
    Route::apiResource('order_messages', OrderMessagesController::class)->names('order_messages.api');
    Route::apiResource('order_status_logs', OrderStatusLogsController::class)->names('order_status_logs.api');
    Route::apiResource('services', ServicesController::class)->names('services.api');
    Route::apiResource('divisions', DivisionsController::class)->names('divisions.api');
    Route::apiResource('users', UsersController::class)->names('users.api');
});

Route::post('/login', [AuthController::class, 'auth'])->name('user.login');
