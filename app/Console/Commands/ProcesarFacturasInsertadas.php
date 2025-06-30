<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('enviados', 'pendiente')
            ->where('estado_proceso', 'desbloqueada')->get();


        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {

                $nif = strtoupper(trim($factura->nif));

                //Probar si el nif es correcto, si no, te lleva al catch
                 if (strlen($factura->nif) !== 9) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} es incorrecto");
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

                //Generar XML
                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                //Probar el catch forzando un error
                //throw new \Exception('Error forzado');

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

                //Vemos si existe la factura comprobando su numero de serie de la factura para que no se duplique
                $exists = DB::table('facturas_firmadas')->where('num_serie_factura', $factura->numSerieFactura)->exists();


                //Si no existe la factura que se guarde en la tabla de facturas firmadas
                if (!$exists) {
                    DB::table('facturas_firmadas')->insert([
                        'num_serie_factura' => $factura->numSerieFactura,
                        'xml_firmado' => $xmlFirmado,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }



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
                    'nombre' => $factura->nombre,
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
