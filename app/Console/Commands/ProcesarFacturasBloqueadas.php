<?php

namespace App\Console\Commands;

use App\Models\Estado_procesos;
use App\Services\BloqueoXmlGenerator;
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
    protected $description = 'Procesar facturas firmadas en XML bloqueadas';

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

                $nif = strtoupper(trim($factura->nif));

                //Probar si el nif es correcto, si no, te lleva al catch
                if (strlen($factura->nif) !== 9) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} no tiene 9 caracteres");
                }

                if (!preg_match('/^[0-9]{8}[A-Z]$/', $nif)) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} tiene un formato inválido");
                }

                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                $num = intval(substr($nif, 0, 8));
                $letraEsperada = $letras[$num % 23];

                if ($nif[8] !== $letraEsperada) {
                    throw new \Exception("El DNI de la factura {$factura->numSerieFactura} tiene una letra de control incorrecta");
                }

                //Generamos el xml y lo guardamos en la carpeta de facturas como: facturasLock_EJEMPLO
                $xml = (new BloqueoXmlGenerator())->generateXml($factura);
                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';
                $ruta = $carpetaOrigen . '\facturasLock_' . $factura->numSerieFactura . '.xml';
                file_put_contents($ruta, $xml);

                //Firmamos el XML y lo guardamos en otra carpeta solo para las firmadas
                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);
                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';
                $rutaDestino = $carpetaDestino . '\facturasFirmadasLock_' . $factura->numSerieFactura . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                //Guardamos la factura si no está en la tabla de facturas_firmadas
                $exists = DB::table('facturas_firmadas')->where('num_serie_factura', $factura->numSerieFactura)->exists();
                if (!$exists) {
                    DB::table('facturas_firmadas')->insert([
                        'num_serie_factura' => $factura->numSerieFactura,
                        'xml_firmado' => $xmlFirmado,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                //Si se ha procesado todo correctamente, la factura se marca como enviada y procesada
                $factura->enviados = 'enviado';
                $factura->estado_proceso = 'procesada';
                $factura->save();

                //Calculamos el tiempo que ha tardado en generarse, sumamos todas las facturas que se han generado en ese minuto y el total del tiempo que han tardado
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;

                //También ponemos que en la tabla facturas se cambie de bloqueada a procesada
                if ($factura->estado_proceso == 'procesada') {
                DB::table('facturas')->update([
                    'enviados' => 'enviado',
                    'error' => null,
                    'estado_proceso' => 'procesada',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }           

            } catch (\Throwable $e) {
                //Si sucede algún error(error de nif, error de conexión, error forzado...) que siga en pendiente, que pase de desbloqueada a bloqueada, se genere el error de porque y se guarde
                $factura->enviados = 'pendiente';
                $factura->estado_proceso = 'bloqueada';
                $factura->error = $e->getMessage();
                $factura->save();
            }
        }

        
        //Cuando se carguen todas las facturas firmadas, se guardan todas, con la media de cuanto han tardado y cuanto tiempo ha tenido que pasar para toda esa cantidad de facturas bloqueadas
        if ($totalFacturas > 0) {
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => $mediaTiempo,
                'periodo' => now()->startOfMinute(),
                'tipo_factura' => 'bloqueadas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    


        $this->info('Facturas bloqueadas procesadas correctamente');
    }
}
