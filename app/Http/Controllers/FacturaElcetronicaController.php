<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\Encriptar;
use App\Models\Emisores;
use App\Services\FacturaXmlElectronica;
use App\Services\FirmaXmlGeneratorElectronica;

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
                'firmada' => 'nullable|integer|in:1,0'
            ]
        );

        $firmada = $data['firmada'] ?? 1;

        // $desencriptador = new Encriptar();
        // $desencriptador->decryptBase64AndSaveFile($xml);

        $factura = Facturas::where('cifEmisor', $data['cif'])
            ->where('serie', $data['serie'])
            ->where('numFactura', $data['numero'])
            ->where('ejercicio', $data['ejercicio'])
            ->first();

        if ($factura) {

            $emisor = Emisores::where('cif', $data['cif'])->first();
            if (!$emisor) {
                return response()->json(['mensaje' => "Emisor no encontrado"]);
            }

            $desencriptador = new Encriptar();
            $passwordCert = $desencriptador->decryptString($emisor->password);
            $desencriptador->decryptBase64AndDownloadPfx($passwordCert, $emisor->certificado, $emisor->cif);

            // Generar el XML usando los datos reales de la factura
            $xml = (new FacturaXmlElectronica())->generateXml($factura);
            $xmlFirmado =  (new FirmaXmlGeneratorElectronica())->firmaXml($xml, $data['cif'],  $passwordCert);


            if ($firmada === 0) {
                $xmlBase64 = base64_encode($xml);
                $encriptado = $desencriptador->encryptBase64InputReturnBase64($xmlBase64);
            } else {
                $xmlBase64 = base64_encode($xmlFirmado);
                $encriptado = $desencriptador->encryptBase64InputReturnBase64($xmlBase64);
            }

            // Guardar XML en storage/app/facturasElectronicas las facturas frimadas
            if ($firmada === 1) {
            $carpetaOrigen = storage_path('facturasElectronicas');
            if (!is_dir($carpetaOrigen)) {
                mkdir($carpetaOrigen, 0755, true);
            }
            $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
            file_put_contents($ruta, $xmlFirmado);
            } elseif ($firmada === 0) {
            $carpetaOrigen = storage_path('facturasElectronicasSinFirmar');
            if (!is_dir($carpetaOrigen)) {
                mkdir($carpetaOrigen, 0755, true);
            }
            $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
            file_put_contents($ruta, $xml);
            }

            if ($factura->estado_registro === 1) {
                return response()->json([
                    'resultado' => true,
                    'factura' => $encriptado
                ]);
            } elseif ($factura->estado_registro === 2) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Factura rechazada por la AEAT"
                ]);
            } else {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Esperar 3 minutos para la siguiente solicitud"
                ]);
            }
        } elseif (!$factura) {
            return response()->json(['mensaje' => "Factura no encontrada"]);
        }
    }
}
