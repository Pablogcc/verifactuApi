<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\BloqueoXmlGenerator;
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

        $facturas = Facturas::where('estado_proceso', 0)
            ->where('estado_registro', 0)->get();

        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {
                //Generar XML
                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                //Guardamos el XML
                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';

                //Creamos la ruta: donde va a estar situado el xml, como va a empezar el nombre del archivo(facturas_F2024-0001) y que acabe por .xml
                $ruta = $carpetaOrigen . '\facturas_' . $factura->nombreCliente . '.xml';
                //Guardamos el xml en la ruta creada
                file_put_contents($ruta, $xml);

                //Firmamos el xml de la factura
                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);



                //Guardamos el XML firmado
                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';

                //Creamos la ruta: donde va a estar situado el xml firmado, como va a empezar el nombre del archivo(factura_firmada_F2024-0001) y que acabe por .xml
                $rutaDestino = $carpetaDestino . '\factura_firmada_' . $factura->nombreCliente . '.xml';
                //Guardamos el xml firmado en la ruta creada
                file_put_contents($rutaDestino, $xmlFirmado);

                //---------------------------------------

                $respuestaXml = $verifactuService->enviarFactura($xml);


                if (!str_starts_with(trim($respuestaXml), '<?xml')) {
                    $factura->estado_proceso = 1;
                    $factura->estado_registro = 0;
                    $factura->error = response()->json([
                        'success' => false,
                        'message' => 'Respuesta no válida',
                        'respuesta' => $respuestaXml,
                    ], 500);

                    $factura->save();
                } else {
                    libxml_use_internal_errors(true);
                    $respuestaXmlObj = simplexml_load_string($respuestaXml);
                    if ($respuestaXmlObj === false) {
                        $erroresXml = libxml_get_errors();
                        //$erroresMensajes = array_map(fn($e) => trim($e->message), $erroresXml);
                        libxml_clear_errors();

                        $factura->estado_registro = 0;
                        $factura->estado_proceso = 1;
                        $factura->error = response()->json([
                            'success' => false,
                            'message' => "Error al parsear la respuesta XML",
                            'errores_xml' => $respuestaXml,
                            'respuesta' => $respuestaXml,
                        ], 500);
                        $factura->save();
                    } else {
                        $ns = $respuestaXmlObj->getNamespaces(true);

                        $resultado = $respuestaXmlObj->xpath('//resultado');
                        if ($resultado && trim((string)$resultado[0]) === 'OK') {
                        }
                    }
                }

                try {
                    $respuestaXmlObj = simplexml_load_string($respuestaXml);
                    $ns = $respuestaXmlObj->getNamespaces(true);
                } catch (\Exception $e) {
                    $factura->estado_registro = 0;
                    $factura->estado_proceso = 1;
                    $factura->error = response()->json([
                        'success' => false,
                        'message' => 'Error al parsear la respuesta de la AEAT',
                        'error' => $e->getMessage()
                    ], 500);
                }

                if (strpos($respuestaXml, '<resultado>OK</;resultado>') !== false) {
                    $factura->estado_registro = 1;
                    $factura->estado_proceso = 0;
                    $factura->error = null;
                } else {
                    $factura->estado_registro = 4;
                    $factura->estado_proceso = 0;
                    $factura->error = json_encode($respuestaXml);
                }

                $factura->save();

                //---------------------------------------

                //Calculamos el tiempo que ha tardado la factura en generarse y en firmarse como xml, en milisegundos
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                //Sumamos todas las facturas que se han generado y firmado en ese minuto
                $totalFacturas++;
                //Sumamos todo el tiempo que han tardado todas las facturas en generarse y en firmarse para luego hacer la media de todas
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                //Si sucede algún error(error de nif, error de conexión, error forzado...) que siga en pendiente, que pase de desbloqueada a bloqueada, se genere el error de porque y se guarde
                $factura->estado_proceso = 1;
                $factura->estado_registro = 0;
                $factura->error = $e->getMessage();
                $factura->save();


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
                    $factura->estado_registro = 1;
                    $factura->estado_proceso = 0;
                    $factura->error = null;
                } elseif ($estadoRegistro === 'Incorrecto') {
                    $mensaje = "Incorrecto: $descripcionError";
                    if (!empty($estadoRegistroDuplicado)) {
                        $mensaje .= " | RegistroDuplicado: $estadoRegistroDuplicado";
                    }
                    $factura->etsado_registro = 3;
                    $factura->estado_proceso = 0;
                    $factura->error = $mensaje;
                } elseif ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                    $factura->estado_registro = 1;
                    $factura->estado_proceso = 0;
                    $factura->error = "AceptadaConErrores: $descripcionError";
                } else {
                    $factura->estado_registro = 0;
                    $factura->estado_proceso = 1;
                    $factura->error = "Respuesta desconocida";
                }

                $factura->save();


                //Calculamos el tiempo que ha tardado la factura en generarse y en firmarse como xml, en milisegundos
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                //Sumamos todas las facturas que se han generado y firmado en ese minuto
                $totalFacturas++;
                //Sumamos todo el tiempo que han tardado todas las facturas en generarse y en firmarse para luego hacer la media de todas
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                //Si sucede algún error(error de nif, error de conexión, error forzado...) que siga en pendiente, que pase de desbloqueada a bloqueada, se genere el error de porque y se guarde
                $factura->estado_registro = 0;
                $factura->estado_proceso = 1;
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

    public function verifactuLcok(Request $request)
    {
        $$verifactuService = new ClientesSOAPVerifactu();

        $facturasLock = Facturas::where('estado_proceso', 'bloqueada')->get();

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturasLock = Facturas::where('estado_proceso', 'bloqueada')
            ->where('estado_registro', 'sinPresentar')->get();

        foreach ($facturasLock as $factura) {
            $inicio = microtime(true);

            try {
                $xml = (new BloqueoXmlGenerator())->generateXml($factura);

                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';
                $ruta = $carpetaOrigen . '\facturasLock_' . $factura->numSerieFactura . '.xml';
                file_put_contents($ruta, $xml);

                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);
                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';
                $rutaDestino = $carpetaDestino . '\facturasFirmadasLock_' . $factura->numSerieFactura . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                $respuestaXml = $verifactuService->enviarFactura($xml);

                if (!str_starts_with(trim($respuestaXml), '<?xml')) {
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'bloqueada';
                    $factura->error = response()->json([
                        'success' => false,
                        'message' => 'La AEAT devolvió una respuesta inválida',
                    ], 500);
                }

                try {
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al parsear la respuesta de la AEAT',
                        'error' => $e->getMessage(),
                    ]);
                }

                $factura->save();
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
                $factura->error = null;

                if ($factura->estado_proceso == 'procesada') {
                    DB::table('facturas')->update([
                        'enviados' => 'enviado',
                        'error' => null,
                        'estado_proceso' => 'procesada',
                        'estado_registro' => 'presentada',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                $factura->enviados = 'pendiente';
                $factura->estado_proceso = 'bloqueada';
                $factura->estado_resgistro = 'sin_presentar';
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
                'tipo_factura' => 'bloqueadas',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $log = DB::table('facturas_logs')->orderBy('created_at', 'desc')->first();

        if ($log) {
            return response()->json([
                'success' => true,
                'message' => 'Facturas presentadas',
                'log' => $log
            ]);
        }
    }

    public function verifactuPrueba(Request $request)
    {
        $verifactuService = new ClientesSOAPVerifactu();

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('estado_proceso', 0)
            ->where('estado_registro', 0)->get();

        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {
                // Generar y guardar XML
                $xml = (new FacturaXmlGenerator())->generateXml($factura);
                $carpetaOrigen = getenv('USERPROFILE') . '\facturas';
                $ruta = $carpetaOrigen . '\facturas_' . $factura->nombreCliente . '.xml';
                file_put_contents($ruta, $xml);

                // Firmar y guardar XML firmado
                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);
                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';
                $rutaDestino = $carpetaDestino . '\factura_firmada_' . $factura->nombreCliente . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                // Enviar factura
                $respuestaXml = $verifactuService->enviarFactura($xml);

                /*if (is_string($respuesta)) {
                    $json = json_decode($respuesta, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['response'])) {
                        $respuestaXml = $json['response'];
                    } else {
                        $respuestaXml = $respuesta;
                    }
                } else {
                    $respuestaXml = $respuesta;
                }*/

                libxml_use_internal_errors(true);
                $respuestaXmlObj = simplexml_load_string($respuestaXml);

                if ($respuestaXmlObj !== false) {
                    $resultado = $respuestaXmlObj->xpath('//resultado');
                    $valorResultado = $resultado ? trim((string)$resultado[0]) : '';

                    $estadoRegistro = '';
                    $descripcionError = '';
                    $aceptadoConErrores = false;

                    $namespaces = $respuestaXmlObj->getNamespaces(true);
                    if (isset($namespaces['tikR'])) {
                        $body = $respuestaXmlObj->children($namespaces['env'])->Body ?? null;
                        $respuestaSII = $body?->children($namespaces['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                        $respuestaLinea = $respuestaSII?->children($namespaces['tikR'])->RespuestaLinea ?? null;
                        $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])->EstadoRegistro ?? '';
                        $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])->DescripcionErrorRegistro ?? '';
                        $registroDuplicado = $respuestaLinea?->children($namespaces['tikR'])->RegistroDuplicado ?? null;
                        $estadoRegistroDuplicado = (string) $registroDuplicado?->children($namespaces['tik'])->EstadoRegistroDuplicado ?? '';
                        if ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                            $aceptadoConErrores = true;
                        }
                    }
                    //$aceptadoConErrores
                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 1;
                        $factura->error = $aceptadoConErrores ? 'Aceptada con errores: ' . $descripcionError : null;
                    } elseif ($estadoRegistro === 'Incorrecto') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = 'Rechazada: ' . $descripcionError;
                    } else {
                        // Añadimos debug para entender qué falló
                        $factura->estado_proceso = 1;
                        $factura->estado_registro = 0;
                        $factura->error = "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - aceptadoConErrores=$aceptadoConErrores - XML bruto: $respuestaXml";
                    }
                } else {
                    $factura->estado_proceso = 1;
                    $factura->estado_registro = 0;
                    $factura->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                }


                $factura->save();

                // Tiempo de proceso
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                // Error general
                $factura->estado_proceso = 1; // bloqueada
                $factura->estado_registro = 0; // sin presentar
                $factura->error = $e->getMessage();
                $factura->save();
            }
        }

        // Guardar logs
        if ($totalFacturas > 0) {
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
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
