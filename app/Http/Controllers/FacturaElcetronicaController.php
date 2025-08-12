<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\Encriptar;
use Illuminate\Support\Facades\Storage;
use App\Services\FacturaXmlElectronica;

class FacturaElcetronicaController extends Controller
{
    public function facturaElectronica(Request $request)
    {
        $data = $request->validate(
            [
                'cif' => 'required|string',
                'serie' => "required|string",
                'numero' => 'required|integer',
                'ejercicio' => 'required|integer',
                'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx'],
                'firmada' => 'required|integer|in:1,0'
            ]
        );

        $firmada = $data['firmada'] ?? '1';

        // $desencriptador = new Encriptar();
        // $desencriptador->decryptBase64AndSaveFile($xml);

        $factura = Facturas::where('cifEmisor', $data['cif'])
            ->where('serie', $data['serie'])
            ->where('numFactura', $data['numero'])
            ->where('ejercicio', $data['ejercicio'])
            ->first();


        if ($factura) {

            // Generar el XML usando los datos reales de la factura
            $xml = (new FacturaXmlElectronica())->generateXml($factura);

            

            // Guardar XML en storage/app/facturasElectronicas
            $carpetaOrigen = storage_path('facturasElectronicas');
            $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
            file_put_contents($ruta, $xml);

            if ($factura->estado_registro === 1) {
                if ($firmada === 1) {
                    return response()->json([
                        'resultado' => true,
                        'factura' => "Factura encriptada firmada",
                        'delete' => $factura
                    ]);
                } else {
                    return response()->json([
                        'resultado' => true,
                        'factura' => "Factura encriptada sin firmar",
                        'delete' => $factura
                    ]);
                }
            } elseif ($factura->estado_registro === 2) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Factura incorrecta"
                ]);
            } else {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Esperar 3 minutos"
                ]);
            }
        } elseif (!$factura) {
            return response()->json(['mensaje' => "Factura no encontrada"]);
        }
    }
}
