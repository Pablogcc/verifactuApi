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

                libxml_use_internal_errors(true);
                $respuestaXmlObj = simplexml_load_string($respuestaXml);

                if ($respuestaXmlObj !== false) {

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
                        $descripcionError2 = (string) $registroDuplicado?->children($namespaces['tik'])->DescripcionErrorRegistro ?? '';
                    }
                    
                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 1;
                        $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores) 
                        ? 'Aceptada con errores: ' . $descripcionError : null;
                    } elseif ($estadoRegistro === 'Incorrecto') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = 'Rechazada: ' . $descripcionError . PHP_EOL . 'Descripción Error Registro Duplicado: ' . $descripcionError2;
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

        $facturasLock = Facturas::where('estado_proceso', 1)
            ->where('estado_registro', 0)->get();

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

   
}
