<?php

namespace App\Http\Controllers;

use App\Models\Emisores;
use App\Models\Facturas;
use Illuminate\Http\Request;
use App\Services\Encriptar;



class CertificadosController extends Controller
{
    public function convertir(Request $request)
    {

        //Recibo por el body el cif del emisor para recoger su certificado
        $data = $request->validate([
            'cif' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);

        //Recogemos todos los campos del cif recibido por el body
        $emisor = Emisores::where('cif', $data['cif'])->first();

        if (!$emisor) {
            return response()->json([
                'success' => false
            ]);
        }

        try {
            $desencriptador = new Encriptar();

            $contrasenna = $desencriptador->decryptString($emisor->password);


            $paths = $desencriptador->decryptBase64AndDownloadPfx($contrasenna, $emisor->certificado, $emisor->cif);

            $certContent = file_get_contents($paths['cert']);
            if ($certContent === false) {
                throw new \Exception("No se pudo leer cert.pem");
            }

            // Convertir en recurso de certificado
            $cert = openssl_x509_read($certContent);
            if ($cert === false) {
                throw new \Exception("No se pudo interpretar el certificado");
            }

            // Extraer info
            $certInfo = openssl_x509_parse($cert);
            if (!$certInfo || !isset($certInfo['validTo_time_t'])) {
                throw new \Exception("No se pudo obtener la fecha de validez del certificado");
            }

            // Guardar fecha de caducidad
            $fechaValidez = date('Y-m-d', $certInfo['validTo_time_t']);
            $emisor->fechaValidez = $fechaValidez;
            $emisor->save();

            return response()->json([
                'respuesta' => true,
                'fechaValidez' => $fechaValidez
            ]);
            ////Ejemplo uso del método encriptar string(Contraseña)
            //$contrasenna = $desencriptador->encryptString("Verifactu");
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage()
            ]);
        }
    }
}
