<?php

use App\Http\Controllers\EmployeeNfcController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['device.token', 'throttle:30,1'])->group(function () {
    Route::post('/nfc/receive', [EmployeeNfcController::class, 'receiveNfc']);
    Route::post('/nfc/scan', [EmployeeNfcController::class, 'scan']);
});
