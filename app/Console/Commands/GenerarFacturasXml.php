<?php

namespace App\Console\Commands;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;

use Illuminate\Console\Command;

class GenerarFacturasXml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'factura:xml:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar Xml de todas las facturas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $facturas = Facturas::all();
        $generator = new FacturaXmlGenerator();

        if(strlen($facturas->nif) !== 9) {
            $this->error("El NIF de la factura {$facturas->numSerieFactura} no tiene 9 caracteres, Proceso detenido para esta factura");
            return;
        }

        foreach ($facturas as $factura) {
            $xml = $generator->generateXml($factura);
            $nombre = $factura->numSerieFactura;

            $carpeta = getenv('USERPROFILE') . '\facturas';

            $ruta = $carpeta . "\\facturas_{$nombre}.xml";
            file_put_contents($ruta, $xml);
            $this->info("Generado: $ruta");
        }

        $this->info('Todos los XML han sido generados');


    }
}
