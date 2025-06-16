<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;

class ProcesarFacturasInsertadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facturas:procesar-inserts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar facturas firmadas en XML';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $facturas = Facturas::where('enviados', 'pendiente')
            ->whereNotIn('numSerieFactura', function ($query) {
                $query->select('num_serie_factura')->from('facturas_firmadas');
            })->get();

        $totalFacturas = 0;
        $totalTiempo = 0;


        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            //Generar XML
            $xml = (new FacturaXmlGenerator())->generateXml($factura);

            //Guardamos el XML
            $carpetaOrigen = getenv('USERPROFILE') . '\facturas';

            /* Si la carpeta de XML no está creada, se crea automáticamente   
        if(!is_dir($carpetaOrigen)) {
            mkdir($carpetaOrigen, 0777, true);
        }
    */

            $ruta = $carpetaOrigen . '\facturas_' . $factura->numSerieFactura . '.xml';
            file_put_contents($ruta, $xml);

            //Firma del XML
            $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);

            //Guardamos el XML firmado
            $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';

            /* Si la carpeta de XML firmados no está creada, se crea automáticamente   
        if(!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0777, true);
        }
        */

            $rutaDestino = $carpetaDestino . '\factura_firmada_' . $factura->numSerieFactura . '.xml';

            file_put_contents($rutaDestino, $xmlFirmado);

            //Guardado en base de datos
            $exists = DB::table('facturas_firmadas')->where('num_serie_factura', $factura->numSerieFactura)->exists();
            if (!$exists) {
                DB::table('facturas_firmadas')->insert([
                    'num_serie_factura' => $factura->numSerieFactura,
                    'xml_firmado' => $xmlFirmado,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            //Cambiamos estado
            $factura->enviados = 'enviado';
            $factura->save();

            $tiempoMs = intval((microtime(true) - $inicio) * 1000);
            $totalFacturas++;
            $totalTiempo += $tiempoMs;
        }

        if ($totalFacturas > 0) {
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => $mediaTiempo,
                'periodo' => now()->startOfMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info('XML firmados correctamente');
    }
}
