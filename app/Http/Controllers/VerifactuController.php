<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\ClientesSOAPVerifactu;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;

class VerifactuController extends Controller
{
    public function verifactu(Request $request)
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

                //---------------------------------------

                $respuestaXml = $verifactuService->enviarFactura($xml);

                if (!str_starts_with(trim($respuestaXml), '<?xml')) {
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'bloqueada';
                    $factura->error = response()->json([
                        'success' => false,
                        'message' => 'La AEAT devolvió una respuesta no válida',
                        'respuesta' => $respuestaXml,
                    ], 500);

                    $factura->save();
                    continue;
                }

                $respuestaXmlObj = simplexml_load_string($respuestaXml);
                //$respuestaXmlObj->registerXPathNamespace('tikR', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');

                $namespaces = $respuestaXmlObj->getNamespaces(true);
                $body = $respuestaXmlObj->children($namespaces['env'])->Body ?? null;

                $respuesta = $body?->children($namespaces['tikR'])->RespuestaRegFacturacion ?? null;

                $respuestaLinea = $respuesta?->children($namespaces['tikR'])->RespuestaLinea ?? null;

                //------------------------------------------------

                //$estadoRegistro = (string)($respuestaXmlObj('//tikR:EstadoRegistro')[0] ?? '');
                $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])->EstadoRegistro ?? '';
                
                //$descripcionError = (string)($respuestaXmlObj->xpath('//tikR:DescripcionErrorRegistro')[0] ?? '');
                $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])->DescripcionErrorRegistro ?? '';

                $registroDuplicado = (string) $respuestaLinea?->children($namespaces['tikR'])->RegistroDuplicado ?? null;

                //$estadoRegistroDuplicado = (string) ($respuestaXmlObj->xpath('//tikR:EstadoRegistroDuplicado'));
                $estadoRegistroDuplicado = (string) $registroDuplicado?->children($namespaces['tik'])->EstadoRegistroDuplicado ?? '';

                if ($estadoRegistro === 'Correcto') {
                    $factura->enviados = 'enviado';
                    $factura->estado_proceso = 'presentada';
                    $factura->error = null;
                } elseif ($estadoRegistro === 'Incorrecto') {
                    $mensaje = "Incorrecto: $descripcionError";
                    if (!empty($estadoRegistroDuplicado)) {
                        $mensaje .= " | RegistroDuplicado: $estadoRegistroDuplicado";
                    }
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'rechazada';
                    $factura->error = $mensaje;
                } elseif ($estadoRegistroDuplicado === 'AceptadaConErrores') {
                    $factura->enviados = 'enviado';
                    $factura->estado_proceso = 'presentada';
                    $factura->error = "AceptadaConErrores: $descripcionError";
                } else {
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'bloqueada';
                    $factura->error = "Respuesta desconocida";
                }

                $factura->save();

                //Cambiamos el estado de la factura, diciendo que se ha enviado y procesado, y lo guardamos

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
                    'serie' => $factura->serie,
                    'numFactura' => $factura->numFactura,
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

        return response()->json([
            'success' => true,
            'message' => "Facturas generadas $totalFacturas",
        ]);
    }
}
