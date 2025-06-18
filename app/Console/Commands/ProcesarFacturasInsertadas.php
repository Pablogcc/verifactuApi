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
        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('enviados', 'pendiente')
            ->where('estado_proceso', 'desbloqueada')
            ->whereNotIn('numSerieFactura', function ($query) {
                $query->select('num_serie_factura')->from('facturas_firmadas');
            })->get();

        foreach ($facturas as $factura) {
            $inicio = microtime(true);


            try {
                //Generar XML
                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                //Probar el catch
                throw new \Exception('Error forzado');

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
                $factura->estado_proceso = 'procesada';
                $factura->save();

                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                $factura->enviados = 'pendiente';
                $factura->estado_proceso = 'bloqueada';
                $factura->error = $e->getMessage();
                $factura->save();

                $data = [
                    'idVersion' => $factura->idVersion,
                    'idEmisorFactura' => $factura->idEmisorFactura,
                    'numSerieFactura' => $factura->numSerieFactura,
                    'fechaExpedicionFactura' => $factura->fechaExpedicionFactura,
                    'refExterna' => $factura->refExterna,
                    'nombreRazonEmisor' => $factura->nombreRazonEmisor,
                    'subsanacion' => $factura->subsanacion,
                    'rechazoPrevio' => $factura->rechazoPrevio,
                    'tipoFactura' => $factura->tipoFactura,
                    'idEmisorFacturaRectificada' => $factura->idEmisorFacturaRectificada,
                    'numSerieFacturaRectificada' => $factura->numSerieFacturaRectificada,
                    'fechaExpedicionFacturaRectificada' => $factura->fechaExpedicionFacturaRectificada,
                    'idEmisorFacturaSustituida' => $factura->idEmisorFacturaSustituida,
                    'numSerieFacturaSustituida' => $factura->numSerieFacturaSustituida,
                    'fechaExpedicionFacturaSustituida' => $factura->fechaExpedicionFacturaSustituida,
                    'baseRectificada' => $factura->baseRectificada,
                    'cuotaRectificada' => $factura->cuotaRectificada,
                    'cuotaRecargoRectificado' => $factura->cuotaRecargoRectificado,
                    'fechaOperacion' => $factura->fechaOperacion,
                    'descripcionOperacion' => $factura->descripcionOperacion,
                    'facturaSimplificadaArt7273' => $factura->facturaSimplificadaArt7273,
                    'facturaSinIdentifDestinatarioArt61d' => $factura->facturaSinIdentifDestinatarioArt61d,
                    'macrodato' => $factura->macrodato,
                    'emitidaPorTerceroODestinatario' => $factura->emitidaPorTerceroODestinatario,
                    'nombreRazon' => $factura->nombreRazon,
                    'nif' => $factura->nif,
                    'codigoPais' => $factura->codigoPais,
                    'idType' => $factura->idType,
                    'id' => $factura->id,
                    'cupon' => $factura->cupon,
                    'impuesto' => $factura->impuesto,
                    'claveRegimen' => $factura->claveRegimen,
                    'calificacionOperacion' => $factura->calificacionOperacion,
                    'operacionExenta' => $factura->operacionExenta,
                    'tipoImpositivo' => $factura->tipoImpositivo,
                    'baseImponibleOimporteNoSujeto' => $factura->baseImponibleOimporteNoSujeto,
                    'baseImponibleACoste' => $factura->baseImponibleACoste,
                    'cuotaRepercutida' => $factura->cuotaRepercutida,
                    'tipoRecargoEquivalencia' => $factura->tipoRecargoEquivalencia,
                    'cuotaRecargoEquivalencia' => $factura->cuotaRecargoEquivalencia,
                    'cuotaTotal' => $factura->cuotaTotal,
                    'importeTotal' => $factura->importeTotal,
                    'primerRegistro' => $factura->primerRegistro,
                    'huella' => $factura->huella,
                    'fechaHoraHusoGenRegistro' => $factura->fechaHoraHusoGenRegistro,
                    'numRegistroAcuerdoFacturacion' => $factura->numRegistroAcuerdoFacturacion,
                    'idAcuerdoSistemaInformatico' => $factura->idAcuerdoSistemaInformatico,
                    'tipoHuella' => $factura->tipoHuella,
                    'enviados' => $factura->enviados,
                    'error' => $e->getMessage(),
                    'estado_proceso' => 'bloqueada',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                

                DB::connection('apibloqueados')->table('estado_procesos')->insert($data);
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

        $this->info('XML firmados correctamente');
    }
}
