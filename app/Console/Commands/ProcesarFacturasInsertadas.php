<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ClientesSOAPVerifactu;


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
    protected $description = 'Procesar facturas firmadas en XML';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $verifactuService = new ClientesSOAPVerifactu();

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('enviados', 'pendiente')
            ->where('estado_proceso', 'desbloqueada')->get();


        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {
                //Generar XML
                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                //Guardamos el XML
                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';

                //Creamos la ruta: donde va a estar situado el xml, como va a empezar el nombre del archivo(facturas_F2024-0001) y que acabe por .xml
                $ruta = $carpetaOrigen . '\facturas_' . $factura->numSerieFactura . '.xml';
                //Guardamos el xml en la ruta creada
                file_put_contents($ruta, $xml);

                //Firmamos el xml de la factura
                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);

                
                
                //Guardamos el XML firmado
                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';

                //Creamos la ruta: donde va a estar situado el xml firmado, como va a empezar el nombre del archivo(factura_firmada_F2024-0001) y que acabe por .xml
                $rutaDestino = $carpetaDestino . '\factura_firmada_' . $factura->numSerieFactura . '.xml';
                //Guardamos el xml firmado en la ruta creada
                file_put_contents($rutaDestino, $xmlFirmado);

                $respuestaXml = $verifactuService->enviarFactura($xml);

                if (!str_starts_with(trim($respuestaXml), '<?xml')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Respuesta no válida',
                        'respuesta' => $respuestaXml,
                    ], 500);
                }

                try {
                    $respuestaXmlObj = simplexml_load_string($respuestaXml);

                    $ns = $respuestaXmlObj->getNamespaces(true);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al parsear la respuesta de la AEAT',
                        'error' => $e->getMessage()
                    ]);
                }

                $resultado = $respuestaXmlObj->children($ns['soapenv'])
                ->Body
                ->children($ns['sum'])
                ->RegFactuSistemaFacturacionResponse
                ->resultado ?? null;
                
                if ((string)$resultado === 'OK') {
                    $factura->enviados = 'enviado';
                    $factura->estado_proceso = 'procesada';
                    $factura->error = null;
                } else {
                    $factura->enviados = 'pendiente';
                    $factura->estado = 'bloqueada';
                    $factura->error = json_encode($respuestaXml);
                }

                //Cambiamos el estado de la factura, diciendo que se ha enviado y procesado, y lo guardamos
                //$factura->enviados = 'enviado';
                //$factura->estado_proceso = 'procesada';
                $factura->save();

                //Calculamos el tiempo que ha tardado la factura en generarse y en firmarse como xml, en milisegundos
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                //Sumamos todas las facturas que se han generado y firmado en ese minuto
                $totalFacturas++;
                //Sumamos todo el tiempo que han tardado todas las facturas en generarse y en firmarse para luego hacer la media de todas
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                //Si sucede algún error(error de nif, error de conexión, error forzado...) que siga en pendiente, que pase de desbloqueada a bloqueada, se genere el error de porque y se guarde
                $factura->enviados = 'pendiente';
                $factura->estado_proceso = 'bloqueada';
                $factura->error = $e->getMessage();
                $factura->save();

                //Luego de que de error lo guardamos en una tabla distinta
                $data = [
                    'idVersion' => $factura->idVersion,
                    'idInstalacion' => $factura->idInstalacion,
                    'idEmisorFactura' => $factura->idEmisorFactura,
                    'numSerieFactura' => $factura->numSerieFactura,
                    'fechaExpedicionFactura' => $factura->fechaExpedicionFactura,
                    'refExterna' => $factura->refExterna,
                    'nombreEmisor' => $factura->nombreEmisor,
                    'cifEmisor' => $factura->cifEmisor,
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
                    'nombreCliente' => $factura->nombreCliente,
                    'nifCliente' => $factura->nifCliente,
                    'codigoPais' => $factura->codigoPais,
                    'idType' => $factura->idType,
                    'cupon' => $factura->cupon,
                    'impuesto' => $factura->impuesto,
                    'claveRegimen' => $factura->claveRegimen,
                    'calificacionOperacion' => $factura->calificacionOperacion,
                    'operacionExenta' => $factura->operacionExenta,
                    'tipoImpositivo' => $factura->tipoImpositivo,
                    'tipoImpositivo2' => $factura->tipoImpositivo2,
                    'tipoImpositivo3' => $factura->tipoImpositivo3,
                    'tipoImpositivo4' => $factura->tipoImpositivo4,
                    'baseImponibleOimporteNoSujeto' => $factura->baseImponibleOimporteNoSujeto,
                    'baseImponibleACoste' => $factura->baseImponibleACoste,
                    'baseImponibleACoste2' => $factura->baseImponibleACoste2,
                    'baseImponibleACoste3' => $factura->baseImponibleACoste3,
                    'baseImponibleACoste4' => $factura->baseImponibleACoste4,
                    'cuotaRepercutida' => $factura->cuotaRepercutida,
                    'cuotaRepercutida2' => $factura->cuotaRepercutida2,
                    'cuotaRepercutida3' => $factura->cuotaRepercutida3,
                    'cuotaRepercutida4' => $factura->cuotaRepercutida4,
                    'tipoRecargoEquivalencia' => $factura->tipoRecargoEquivalencia,
                    'tipoRecargoEquivalencia2' => $factura->tipoRecargoEquivalencia2,
                    'tipoRecargoEquivalencia3' => $factura->tipoRecargoEquivalencia3,
                    'tipoRecargoEquivalencia4' => $factura->tipoRecargoEquivalencia4,
                    'cuotaRecargoEquivalencia' => $factura->cuotaRecargoEquivalencia,
                    'cuotaRecargoEquivalencia2' => $factura->cuotaRecargoEquivalencia2,
                    'cuotaRecargoEquivalencia3' => $factura->cuotaRecargoEquivalencia3,
                    'cuotaRecargoEquivalencia4' => $factura->cuotaRecargoEquivalencia4,
                    'claveRegimenSegundo' => $factura->claveRegimenSegundo,
                    'calificacionOperacionSegundo' => $factura->calificacionOperacionSegundo,
                    'tipoImpositivoSegundo' => $factura->tipoImpositivoSegundo,
                    'baseImponibleOimporteNoSujetosegundo' => $factura->baseImponibleOimporteNoSujetosegundo,
                    'cuotaRepercutidaSegundo' => $factura->cuotaRepercutidaSegundo,
                    'cuotaTotal' => $factura->cuotaTotal,
                    'importeTotal' => $factura->importeTotal,
                    'primerRegistro' => $factura->primerRegistro,
                    'IDEmisorFacturaAnterior' => $factura->IDEmisorFacturaAnterior,
                    'numSerieFacturaAnterior' => $factura->numSerieFacturaAnterior,
                    'FechaExpedicionFacturaFacturaAnterior' => $factura->FechaExpedicionFacturaFacturaAnterior,
                    'huellaAnterior' => $factura->huellaAnterior,
                    'fechaHoraHusoGenRegistro' => $factura->fechaHoraHusoGenRegistro,
                    'numRegistroAcuerdoFacturacion' => $factura->numRegistroAcuerdoFacturacion,
                    'idAcuerdoSistemaInformatico' => $factura->idAcuerdoSistemaInformatico,
                    'tipoHuella' => $factura->tipoHuella,
                    'huella' => $factura->huella,
                    'nombreFabricanteSoftware' => $factura->nombreFabricanteSoftware,
                    'nifFabricanteSoftware' => $factura->nifFabricanteSoftware,
                    'nombreSoftware' => $factura->nombreSoftware,
                    'identificadorSoftware' => $factura->identificadorSoftware,
                    'versionSoftware' => $factura->versionSoftware,
                    'numeroInstalacion' => $factura->numeroInstalacion,
                    'tipoUsoPosibleVerifactu' => $factura->tipoUsoPosibleVerifactu,
                    'tipoUsoPosibleMultiOT' => $factura->tipoUsoPosibleMultiOT,
                    'indicadorMultiplesOT' => $factura->indicadorMultiplesOT,
                    'enviados' => $factura->enviados,
                    'error' => $e->getMessage(),
                    'estado_proceso' => 'bloqueada',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                //$exists2 = DB::table('estado_procesos')->where('num_serie_factura', $factura->numSerieFactura)->exists();
                DB::table('estado_procesos')->insert($data);
            }
        }

        // Aquí guardamos los logs de las facturas, si hay facturas, entonces que se guarden
        if ($totalFacturas > 0) {
            //Hacemos la media de todo el tiempo que han durado las facturas, entre el total de facturas
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
            //Insertamos los datos en la tabla de facturas_logs
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => $mediaTiempo,
                'periodo' => now()->startOfMinute(),
                'tipo_factura' => 'desbloqueadas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info('XML firmados correctamente');
    }
}
