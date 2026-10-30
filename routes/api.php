<?php

use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('vehicles', VehicleController::class);
