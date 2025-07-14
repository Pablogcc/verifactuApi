<?php

namespace App\Http\Controllers;

use App\Models\Estado_procesos;
use Illuminate\Http\Request;
use App\Services\BloqueoXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesSOAPVerifactu;

class VerifactuLockController extends Controller
{
    public function verifactuLock(Request $request)
    {
        $verifactuService = new ClientesSOAPVerifactu();

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturasLock = Estado_procesos::where('enviados', 'pendiente')
            ->where('estado_proceso', 'bloqueada')->get();

        foreach ($facturasLock as $factura) {
            $inicio = microtime(true);

            try {

                $nif = strtoupper(trim($factura->nif));

                if (strlen($factura->nif) !== 9) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} es incorrecto");
                }

                if (!preg_match('/^[0-9]{8}[A-Z]$/', $nif)) {
                    throw new \Exception("El NIF de la factura {$factura->numSerieFactura} es incorrecto");
                }

                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                $num = intval(substr($nif, 0, 8));
                $letraEsperada = $letras[$num % 23];

                if ($nif[8] !== $letraEsperada) {
                    throw new \Exception("El DNI de la factura {$factura->numSerieFactura} es incorrecto");
                }

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
                        'message' => 'La AEAT devolvió una respuesta no válida',
                    ], 500);
                    $factura->save();
                }

                try {
                    $respuestaXmlObj = simplexml_load_string($respuestaXml);

                    $ns = $respuestaXmlObj->getNamespaces(true);
                } catch(\Exception $e) {
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'bloqueada';
                    $factura->error = response()->json([
                        'success' => false,
                        'message' => 'Error al parsear la respuesta de la AEAT',
                        'error' => $e->getMessage()
                    ], 500);
                }

                $resultado = $respuestaXmlObj->children($ns['soapenv'])
                ->Body
                ->children($ns['sum'])
                ->RegFactuSistemaFacturacionesResponse
                ->Resultado ?? null;

                if ((string)$resultado === 'OK') {
                    $factura->enviados = 'enviado';
                    $factura->estado_proceso = 'procesada';
                    $factura->error = null;
                } else  {
                    $factura->enviados = 'pendiente';
                    $factura->estado_proceso = 'bloqueada';
                    $factura->error = json_encode($respuestaXml);
                }

                //$factura->enviados = 'enviado';
                //$factura->estado_proceso = 'procesada';
                $factura->save();

                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;

                if ($factura->estado_proceso == 'procesada') {
                    DB::table('facturas')->update([
                        'enviados' => 'enviado',
                        'error' => null,
                        'estado_proceso' => 'procesada',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
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
                'tipo_factura' => 'bloqueadas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $log = DB::table('facturas_logs')->orderBy('created_at', 'desc')->first();

        if ($log) {
            return response()->json([
                'success' => true,
                'message' => "Facturas desbloqueadas y firmadas con éxito",
                'data' => $log
            ]);
        }
    }
}
