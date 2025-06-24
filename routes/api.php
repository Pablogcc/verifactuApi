<?php

use App\Console\Commands\ConsultaCDI;
use App\Http\Controllers\ConsultaCDIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturasController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/facturas', [FacturasController::class, 'getAll']);

Route::get('/factura/{id}', [FacturasController::class, 'getById']);

Route::post('/procesarFacturas', [FacturasController::class, 'procesarFacturas']);

Route::get('/validation/{nif}', [ConsultaCDIController::class, 'validateDNI']);

Route::get('/validationdni/{numSerieFactura}', [ConsultaCDIController::class, 'validatedni']);