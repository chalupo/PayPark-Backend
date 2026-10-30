<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('vehicles', VehicleController::class);

Route::get('accesses', [AccessController::class, 'index']);
Route::post('accesses/entry', [AccessController::class, 'entry']);
Route::post('accesses/{vehicle}/exit', [AccessController::class, 'exit']);
Route::get('accesses/{access}', [AccessController::class, 'show']);
Route::get('availability', [AccessController::class, 'availability']);

Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store']);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
});
