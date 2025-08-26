<?php

use App\Console\Commands\ConsultaCDI;
use App\Http\Controllers\CertificadosController;
use App\Http\Controllers\ConsultaCDIController;
use App\Http\Controllers\FacturaElcetronicaController;
use App\Http\Controllers\FechaHoraServerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VerifactuController;
use App\Http\Middleware\TokenIdentificado;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Ruta para comprobar el la validación del dni: http://127.0.0.1:8000/api/validaciondni o https://verifactu.conecta365.com/api/validaciondni
Route::post('validaciondni', [ConsultaCDIController::class, 'validarDNI']);

//Ruta para comprobar y firmar las facturas bloqueadas: http://127.0.0.1:8000/api/generateVerifactuLock
Route::post('generateVerifactuLock', [VerifactuController::class, 'verifactuLock']);

//Ruta para comprobar y firmar las facturas: http://127.0.0.1:8000/api/generateVerifactu?sZQe4cxaEWeFBe3EPkeah0KqowVBLx=sZQe4cxaEWeFBe3EPkeah0KqowVBLx
Route::post('generateVerifactu', [VerifactuController::class, 'verifactuPrueba']);

Route::post('convertirCertificados',  [CertificadosController::class, 'convertir']);

Route::get('fechaHoraActual',  [FechaHoraServerController::class, 'fechaHoraZonaHoraria']);

Route::post('facturaElectronica', [FacturaElcetronicaController::class, 'facturaElectronica']);

Route::get('notificacionCertificado', [CertificadosController::class, 'notificacion']);
