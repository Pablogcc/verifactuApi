<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Cada minuto ejecuta el comando(ProcesarFacturasInsertadas.php)
Schedule::command('facturas:procesar-inserts')->everyMinute();

// Para probar cada 10 segundos
//Schedule::command('facturas:procesar-inserts')->everyTenSeconds();