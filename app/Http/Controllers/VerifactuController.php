<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Models\Emisores;
use App\Services\BloqueoXmlGenerator;
use App\Services\ClientesSOAPVerifactu;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;

class VerifactuController extends Controller
{

    public function verifactuPrueba(Request $request)
    {
        $token = $request->query('token');

        $verifactuService = new ClientesSOAPVerifactu();


        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('estado_proceso', 0)
            ->where('estado_registro', 0)->get();

        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {
                $numero = $factura->numFactura;
                $serie = $factura->serie;
                $fechaEjercicio = $factura->ejercicio;
                $cifEmisor = $factura->idEmisorFactura;

                if ($numero > 1) {
                    $numFacturaAnterior = $numero - 1;

                    // Busca la factura anterior con misma serie
                    $facturaAnterior = Facturas::where('serie', $serie)
                        ->where('numFactura', $numFacturaAnterior)
                        ->where('ejercicio', $fechaEjercicio)
                        ->where('idEmisorFactura', $cifEmisor)
                        ->first();

                    if ($facturaAnterior) {
                        $factura->IDEmisorFacturaAnterior = $facturaAnterior->idEmisorFactura;
                        $factura->numSerieFacturaAnterior = $facturaAnterior->numSerieFactura;
                        $factura->FechaExpedicionFacturaAnterior = $facturaAnterior->fechaExpedicionFactura;
                    } else {
                        // No encontrada: deja vacíos o nulos
                        $factura->IDEmisorFacturaAnterior = '';
                        $factura->numSerieFacturaAnterior = '';
                        $factura->FechaExpedicionFacturaAnterior = '';
                    }
                } else {
                    // Primera factura: no tiene anterior
                    $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                    $factura->numSerieFacturaAnterior = $factura->serie . '/0000000';
                    $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                    $factura->huellaAnterior = $factura->huella;
                }

                // Generar y guardar XML(Storage)
                $xml = (new FacturaXmlGenerator())->generateXml($factura);
                $carpetaOrigen = storage_path('facturas');
                $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($ruta, $xml);

                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);
                $carpetaDestino = storage_path('facturasFirmadas');
                $rutaDestino = $carpetaDestino . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                // Enviar factura
                //Paso por parámetros el cif de la factura para actualizar la ruta de almacenamiento de certificado
                $verifactuService->actualizarRutas($factura->idEmisorFactura);
                $respuestaXml = $verifactuService->enviarFactura($xml);

                libxml_use_internal_errors(true);
                $respuestaXmlObj = simplexml_load_string($respuestaXml);

                if ($respuestaXmlObj !== false) {
                    $namespaces = $respuestaXmlObj->getNamespaces(true);
                    $body = $respuestaXmlObj->children($namespaces['env'])->Body ?? null;

                    $faultString = '';
                    if ($body) {
                        $faultNodes = $body->children($namespaces['env'])->Fault ?? null;
                        if ($faultNodes) {
                            foreach ($faultNodes->children() as $child) {
                                if ($child->getName() === 'faultstring') {
                                    $faultString = (string) $child;
                                    break;
                                }
                            }
                        }
                    }

                    $estadoRegistro = '';
                    $descripcionError = '';
                    $aceptadoConErrores = false;
                    $descripcionError2 = '';

                    if (isset($namespaces['tikR'])) {
                        $respuestaSII = $body?->children($namespaces['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                        $respuestaLinea = $respuestaSII?->children($namespaces['tikR'])->RespuestaLinea ?? null;

                        $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])->EstadoRegistro ?? '';
                        $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])->DescripcionErrorRegistro ?? '';

                        $registroDuplicado = $respuestaLinea?->children($namespaces['tikR'])->RegistroDuplicado ?? null;
                        $estadoRegistroDuplicado = (string) $registroDuplicado?->children($namespaces['tik'])->EstadoRegistroDuplicado ?? '';
                        if ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                            $aceptadoConErrores = true;
                        }
                        $descripcionError2 = (string) $registroDuplicado?->children($namespaces['tik'])->DescripcionErrorRegistro ?? '';
                    }

                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 1;
                        $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                            ? 'Aceptada con errores: ' . $descripcionError
                            : null;
                    } elseif ($estadoRegistro === 'Incorrecto') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = 'Rechazada: ' . $descripcionError . PHP_EOL . 'Descripción Error Registro Duplicado: ' . $descripcionError2;
                    } else {

                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = $faultString
                            ? "Respuesta no reconocida: $faultString"
                            : "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - aceptadoConErrores=$aceptadoConErrores - XML bruto: $respuestaXml";
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
                $factura->estado_proceso = 1;
                $factura->estado_registro = 0;
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

        if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'success' => true,
                'message' => "Facturas generadas $totalFacturas",
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Token incorrecto"
            ]);
        }
    }

    public function pruebaCert(Request $request)
    {
        $data = $request->validate([
            'cif' => 'required|string'
        ]);

        $emisor = Emisores::where('cif', $data['cif'])->first();

        $fechaCaducidad = $emisor->fechaValidez;
        $hoy = date('Y-m-d');

        if ($fechaCaducidad >= $hoy) {
            return response()->json([
                'success' => true,
                'message' => "El certificado no está caducado"
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "El certificado está caducado"
            ]);
        }
    }



    public function verifactuLock(Request $request)
    {
        $token = $request->query('token');



        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturasLock = Facturas::where('estado_proceso', 1)
            ->where('estado_registro', 0)->get();

        foreach ($facturasLock as $factura) {
            $inicio = microtime(true);

            $verifactuService = new ClientesSOAPVerifactu($facturasLock->idEmisorFactura);

            try {
                $numero = $factura->numFactura;
                $serie = $factura->serie;
                $fechaEjercicio = $factura->ejercicio;
                $cifEmisor = $factura->idEmisorFactura;

                if ($numero > 1) {
                    $numFacturaAnterior = $numero - 1;

                    // Busca la factura anterior con misma serie
                    $facturaAnterior = Facturas::where('serie', $serie)
                        ->where('numFactura', $numFacturaAnterior)
                        ->where('ejercicio', $fechaEjercicio)
                        ->where('idEmisorFactura', $cifEmisor)
                        ->first();

                    if ($facturaAnterior) {
                        $factura->IDEmisorFacturaAnterior = $facturaAnterior->idEmisorFactura;
                        $factura->numSerieFacturaAnterior = $facturaAnterior->numSerieFactura;
                        $factura->FechaExpedicionFacturaAnterior = $facturaAnterior->fechaExpedicionFactura;
                    } else {
                        // No encontrada: deja vacíos o nulos
                        $factura->IDEmisorFacturaAnterior = '';
                        $factura->numSerieFacturaAnterior = '';
                        $factura->FechaExpedicionFacturaAnterior = '';
                    }
                } else {
                    // Primera factura: no tiene anterior
                    $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                    $factura->numSerieFacturaAnterior = $factura->serie . '/0000000';
                    $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                    $factura->huellaAnterior = $factura->huella;
                }

                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                $carpetaOrigen = storage_path('facturas');
                $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($ruta, $xml);

                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml, $factura->idEmisorFactura);
                $carpetaDestino = storage_path('facturasFirmadas');
                $rutaDestino = $carpetaDestino . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                $respuestaXml = $verifactuService->enviarFactura($xml);

                libxml_use_internal_errors(true);
                $respuestaXmlObj = simplexml_load_string($respuestaXml);

                if ($respuestaXmlObj !== false) {
                    $namespaces = $respuestaXmlObj->getNamespaces(true);
                    $body = $respuestaXmlObj->children($namespaces['env'])->Body ?? null;

                    $faultString = '';
                    if ($body) {
                        $faultNodes = $body->children($namespaces['env'])->Fault ?? null;
                        if ($faultNodes) {
                            foreach ($faultNodes->children() as $child) {
                                if ($child->getName() === 'faultstring') {
                                    $faultString = (string) $child;
                                    break;
                                }
                            }
                        }
                    }

                    $estadoRegistro = '';
                    $descripcionError = '';
                    $aceptadoConErrores = false;
                    $descripcionError2 = '';

                    if (isset($namespaces['tikR'])) {
                        $respuestaSII = $body?->children($namespaces['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                        $respuestaLinea = $respuestaSII?->children($namespaces['tikR'])->RespuestaLinea ?? null;

                        $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])->EstadoRegistro ?? '';
                        $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])->DescripcionErrorRegistro ?? '';

                        $registroDuplicado = $respuestaLinea?->children($namespaces['tikR'])->RegistroDuplicado ?? null;
                        $estadoRegistroDuplicado = (string) $registroDuplicado?->children($namespaces['tik'])->EstadoRegistroDuplicado ?? '';
                        if ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                            $aceptadoConErrores = true;
                        }
                        $descripcionError2 = (string) $registroDuplicado?->children($namespaces['tik'])->DescripcionErrorRegistro ?? '';
                    }

                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 1;
                        $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                            ? 'Aceptada con errores: ' . $descripcionError
                            : null;
                    } elseif ($estadoRegistro === 'Incorrecto') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = 'Rechazada: ' . $descripcionError . PHP_EOL . 'Descripción Error Registro Duplicado: ' . $descripcionError2;
                    } else {

                        $factura->estado_proceso = 1;
                        $factura->estado_registro = 0;
                        $factura->error = $faultString
                            ? "Respuesta no reconocida: $faultString"
                            : "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - aceptadoConErrores=$aceptadoConErrores - XML bruto: $respuestaXml";
                    }
                } else {
                    $factura->estado_proceso = 1;
                    $factura->estado_registro = 0;
                    $factura->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                }

                $factura->save();

                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
            } catch (\Exception $e) {
                $factura->estado_proceso = 1;
                $factura->estado_registro = 0;
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

        if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'success' => true,
                'message' => "Facturas generadas $totalFacturas"
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Token incorrecto'
            ]);
        }
    }
}
