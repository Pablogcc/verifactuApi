<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;

class ProcesarFacturasInsertadasTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     */
    public function testExample(): void
    {
        $this->browse(function (Browser $browser) {
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

                //Cambiamos el estado de la factura, diciendo que se ha enviado y procesado, y lo guardamos
                $factura->enviados = 'enviado';
                $factura->estado_proceso = 'procesada';
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
                    'id' => $factura->id,
                    'cupon' => $factura->cupon,
                    'impuesto' => $factura->impuesto,
                    'claveRegimen' => $factura->claveRegimen,
                    'calificacionOperacion' => $factura->calificacionOperacion,
                    'operacionExenta' => $factura->operacionExenta,
                    'tipoImpositivo' => $factura->tipoImpositivo,
                    'baseImponibleOimporteNoSujeto' => $factura->baseImponibleOimporteNoSujeto,
                    'baseImponibleACoste' => $factura->baseImponibleACoste,
                    'baseImponibleACoste2' => $factura->baseImponibleACoste2,
                    'baseImponibleACoste3' => $factura->baseImponibleACoste3,
                    'cuotaRepercutida' => $factura->cuotaRepercutida,
                    'tipoRecargoEquivalencia' => $factura->tipoRecargoEquivalencia,
                    'cuotaRecargoEquivalencia' => $factura->cuotaRecargoEquivalencia,
                    'cuotaTotal' => $factura->cuotaTotal,
                    'importeTotal' => $factura->importeTotal,
                    'primerRegistro' => $factura->primerRegistro,
                    'huellaAnterior' => $factura->huella,
                    'fechaHoraHusoGenRegistro' => $factura->fechaHoraHusoGenRegistro,
                    'numRegistroAcuerdoFacturacion' => $factura->numRegistroAcuerdoFacturacion,
                    'idAcuerdoSistemaInformatico' => $factura->idAcuerdoSistemaInformatico,
                    'tipoHuella' => $factura->tipoHuella,
                    'nombreSoftware' => $factura->nombreSoftware,
                    'versionSoftware' => $factura->versionSoftware,
                    'nombreFabricanteSoftware' => $factura->nombreFabricanteSoftware,
                    'identificadorSoftware' => $factura->identificadorSoftware,
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

        $browser->pause(1000);

        });
    }
}
