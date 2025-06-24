<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;


class ConsultaCDI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:consulta-c-d-i';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $generador = new \App\Services\GeneradorXMLConsultaCDI();
        $xmlConsulta = $generador->generar('48456925L', 'BARBERA FERNANDEZ ALBERTO');

        $clienteCDI = new \App\Services\ClienteSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($xmlConsulta, true);

        echo $respuesta;

        //Para saber el Log que nos manda la AEAT
        //\Log::info('Respuesta CDI AEAT: ' . print_r($respuesta, true));
    }
}
