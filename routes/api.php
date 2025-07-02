<?php

use App\Console\Commands\ConsultaCDI;
use App\Http\Controllers\ConsultaCDIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VerifactuController;
use App\Http\Controllers\VerifactuLockController;
use App\Http\Middleware\TokenIdentificado;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Ruta para comprobar el controller: http://127.0.0.1:8000/api/validaciondni/F2024-0001
Route::get('/validaciondni', [ConsultaCDIController::class, 'validate']);

Route::get('generateVerifactu', [VerifactuController::class, 'verifactu']);

Route::get('generateVerifactuLock', [VerifactuLockController::class, 'verifactuLock']);
