<?php

use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('users', [UsersController::class, 'index']);
});

Route::post('/login', [AuthController::class, 'auth']);