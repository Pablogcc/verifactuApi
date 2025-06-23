<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Cada minuto ejecuta el comando(ProcesarFacturasInsertadas.php)
Schedule::command('facturas:procesar-inserts')->everyMinute();

//Cada 10 minutos ejecuta el comando(ProcesarFacturadasBloqueadas.php)
Schedule::command('facturas:procesar-bloqueadas')->everyTenMinutes();


//Comando para ejecutar en la terminal: 
//php artisan schedule:work