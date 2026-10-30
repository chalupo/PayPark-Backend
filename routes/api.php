<?php

use App\Http\Controllers\AccessController;
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
