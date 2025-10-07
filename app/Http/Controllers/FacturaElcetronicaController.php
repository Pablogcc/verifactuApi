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
        //Recogemos todos los datos necesarios en un JSON de la factura
        //En el JSON se puedes decidir si quieres que tu factura sea firmada(1) o no(0)
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

        //Buscamos la factura que tenga ese cif, serie, numFactura y ejercicio.
        //Y recogemos el primero que tiene
        $factura = Facturas::where('cifEmisor', $data['cif'])
            ->where('serie', $data['serie'])
            ->where('numFactura', $data['numero'])
            ->where('ejercicio', $data['ejercicio'])
            ->first();

        //Si la factura existe, comprobamnos si está su certificado en la tabla emisores
        if ($factura) {
            $emisor = Emisores::where('cif', $data['cif'])->first();
            if (!$emisor) {
                return response()->json(['mensaje' => "Emisor no encontrado"]);
            }

            //Llamamos al servicio para desencriptar el certificado y la contraseña
            $desencriptador = new Encriptar();
            $passwordCert = $desencriptador->decryptString($emisor->password);
            $desencriptador->decryptBase64AndDownloadPfx($passwordCert, $emisor->certificado, $emisor->cif);

            // Generar el XML usando los datos reales de la factura
            $xml = (new FacturaXmlElectronica())->generateXml($factura);
            $xmlFirmado =  (new FirmaXmlGeneratorElectronica())->firmaXml($xml, $data['cif'],  $passwordCert);

            //Dependiendo de si la factura va a estar firmada o no, se creará un tipo de xml distinto
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
                //Si no, guardamos el XML en storage/app/facturasElectronicasSinFirmar
            } elseif ($firmada === 0) {
                $carpetaOrigen = storage_path('facturasElectronicasSinFirmar');
                if (!is_dir($carpetaOrigen)) {
                    mkdir($carpetaOrigen, 0755, true);
                }
                $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($ruta, $xml);
            }

            //Si la factura es correcta, entonces se genera la encriptación y el XML correctamente
            if ($factura->estado_registro === 1) {
                return response()->json([
                    'resultado' => true,
                    'factura' => $encriptado
                ]);
                //Si la factur es incorrecta, no se encripta ni se genera el XML
            } elseif ($factura->estado_registro === 2) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Factura rechazada por la AEAT"
                ]);
            } else {
                //Si la factura no está comprobada por la AEAT, entonces deberás esperar 3 minutos
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Esperar 3 minutos para la siguiente solicitud"
                ]);
            }
            //Si la factura no está en la base de datos, entonces saldrá un mensaje de factura no encontrada
        } elseif (!$factura) {
            return response()->json(['mensaje' => "Factura no encontrada"]);
        }
    }
}
