<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\FacturaXmlGenerator;
use App\Services\FirmaXmlGenerator;
use Illuminate\Support\Facades\DB;

class VerifactuController extends Controller
{
    public function verifactu(Request $request)
    {

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('enviados', 'pendiente')
            ->where('estado_proceso', 'desbloqueada')->get();

        foreach ($facturas as $factura) {
            $inicio = microtime(true);

            try {
                $xml = (new FacturaXmlGenerator())->generateXml($factura);

                $carpeta = getenv('USERPROFILE') . '\facturas';
                $ruta = $carpeta . '\facturas_' . $factura->numSerieFactura . '.xml';
                file_put_contents($ruta, $xml);

                $xmlFirmado = (new FirmaXmlGenerator())->firmaXml($xml);

                $carpetaDestino = getenv('USERPROFILE') . '\facturasFirmadas';
                $rutaDestino = $carpetaDestino . '\factura_firmada_' . $factura->numSerieFactura . '.xml';
                file_put_contents($rutaDestino, $xmlFirmado);

                $factura->enviados = 'enviado';
                $factura->estado_proceso = 'procesada';
                $factura->save();

                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
            } catch (\Exception $e) {
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

                DB::table('estado_procesos')->insert($data);
            }
        }

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

    }
}
