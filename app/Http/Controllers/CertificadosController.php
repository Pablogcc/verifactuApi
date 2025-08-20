<?php

namespace App\Http\Controllers;

use App\Models\Emisores;
use Illuminate\Http\Request;
use App\Services\Encriptar;
use DateTime;

class CertificadosController extends Controller
{
    public function convertir(Request $request)
    {

        //Recibo por el body el cif del emisor para recoger su certificado
        $data = $request->validate([
            'cif' => 'required|string',
            'certificado' => 'required|string',
            'password' => 'required|string',
            'correoAdministrativo' => 'required|string',
            'nombreEmpresa' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);

        try {
            $desencriptador = new Encriptar();

            $contrasenna = $desencriptador->decryptString($data['password']);


            $paths = $desencriptador->decryptBase64AndDownloadPfx($contrasenna, $data['certificado'], $data['cif']);

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

            $nifCertificado = $certInfo['subject']['serialNumber'] ?? null;

            if (!$nifCertificado) {
                throw new \Exception("El certificado no contiene un NIF/CIF válido");
            }

            // Limpiar prefijo (ej. "IDCES-48456925L" → "48456925L")
            $nifCertificadoLimpio = preg_replace('/^[A-Z\-]+/', '', $nifCertificado);

            // Comparar NIF/CIF recibido con el del certificado
            if (strtoupper(trim($nifCertificadoLimpio)) !== strtoupper(trim($data['cif']))) {
                throw new \Exception("El NIF no coincide con el del certificado digital");
            }

            $hoy = new DateTime();
            $fechaExpira = new \DateTime($fechaValidez);
            $diasRestantes = (int)$hoy->diff($fechaExpira)->format('%r%a');

            if ($diasRestantes < 0) {
                throw new \Exception("El certificado ya ha caducado el $fechaValidez");
            }

            if (Emisores::where('cif', $data['cif'])->exists()) {
            return response()->json([
                'validado' => 'no',
                'mensaje' => "El emisor con CIF {$data['cif']} ya existe en la base de datos"
            ]);
        }

            Emisores::create([
                'cif' => $data['cif'],
                'certificado' => $data['certificado'],
                'password' => $data['password'],
                'correoAdministrativo' => $data['correoAdministrativo'],
                'nombreEmpresa' => $data['nombreEmpresa'],
                'fechaValidez' => $fechaValidez
            ]);

            if ($diasRestantes <= 20) {
                return response()->json([
                'validado' => 'si',
                'fechaValidez' => $fechaValidez,
                'mensaje' => "El certificado caduca en menos de 20 días"
            ]);
            }

            return response()->json([
                'validado' => 'si',
                'fechaValidez' => $fechaValidez
            ]);

            //Ejemplo uso del método encriptar string(Contraseña)
            //$contrasenna = $desencriptador->encryptString("Verifactu");
        } catch (\Throwable $e) {
            return response()->json([
                'validado' => 'no',
                'fechaValidez' => $fechaValidez,
                'mensaje' => $e->getMessage()
            ]);
        }
    }
}
