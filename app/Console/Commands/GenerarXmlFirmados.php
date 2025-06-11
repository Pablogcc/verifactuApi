<?php

namespace App\Console\Commands;

use App\Services\FirmaXmlGenerator;
use Illuminate\Console\Command;

class GenerarXmlFirmados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firma:xml';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar Xml firmado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $carpeta = getenv('USERPROFILE') . '/facturas';
        $signer = new FirmaXmlGenerator();

        foreach(glob($carpeta . '\facturas_*.xml') as $archivo) {
            $xmlContent = file_get_contents($archivo);
            $xmlFirmado = $signer->firmaXml($xmlContent);

            $firmadoPath = $carpeta . '\facturasFirmadas';
            file_put_contents($firmadoPath, $xmlFirmado);

            $firmadoPath = $carpeta . '\firmado_' . basename($archivo);
            $this->info("El Xml fue firmado en ");


        }

        $this->info('Todos los xml han sido firmados');

    }
}
