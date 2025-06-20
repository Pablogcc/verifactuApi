<?php

namespace App\Console\Commands;

use App\Models\Estado_procesos;
use App\Models\Facturas;
use App\Services\BloqueoXmlGenerator;
//use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcesarFacturasBloqueadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facturas:procesar-bloqueadas';

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
        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturasLock = Estado_procesos::where('enviados', 'pendiente')
            ->where('estado_proceso', 'bloqueada')->get();

        foreach ($facturasLock as $factura) {
            $inicio = microtime(true);

            try {

                if (strlen($factura->nif) !== 9) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} no tiene 9 caracteres");
                }

                $xml = (new BloqueoXmlGenerator())->generateXml($factura);

                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';

                $ruta = $carpetaOrigen . '\facturasLock_' . $factura->numSerieFactura . '.xml';

                file_put_contents($ruta, $xml);

                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);

                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';

                $rutaDestino = $carpetaDestino . '\facturasFirmadasLock_' . $factura->numSerieFactura . '.xml';

                file_put_contents($rutaDestino, $xmlFirmado);


                $exists = DB::table('facturas_firmadas')->where('num_serie_factura', $factura->numSerieFactura)->exists();

                if (!$exists) {
                    DB::table('facturas_firmadas')->insert([
                        'num_serie_factura' => $factura->numSerieFactura,
                        'xml_firmado' => $xmlFirmado,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $factura->enviados = 'enviado';

                $factura->estado_proceso = 'procesada';
                $factura->save();

                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;

                if ($factura->estado_proceso == 'bloqueada') {
                DB::table('facturas')->update([
                    'enviados' => 'enviado',
                    'estado_proceso' => 'procesada',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            } catch (\Throwable $e) {
                $factura->enviados = 'pendiente';
                $factura->estado_proceso = 'bloqueada';
                $factura->error = $e->getMessage();
                $factura->save();
            }
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
    }
}
