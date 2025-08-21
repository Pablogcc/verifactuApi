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

        $fechaValidez = null;

        //Recibo por el body el cif del emisor, el certificado, la contraseña del certificado, el correo de la empresa, el nombre de la empresa y el token
        $data = $request->validate([
            'cif' => 'required|string',
            'certificado' => 'required|string',
            'password' => 'required|string',
            'correoAdministrativo' => 'required|string',
            'nombreEmpresa' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);

        try {
            //Llamamos al servicio de encriptar
            //Usamos los métodos de desencriptar, para desencriptar la contraseña y el certificado para convertirlo en .pem
            $desencriptador = new Encriptar();

            $contrasenna = $desencriptador->decryptString($data['password']);

            $paths = $desencriptador->decryptBase64AndDownloadPfx($contrasenna, $data['certificado'], $data['cif']);

            //Obtenemos el certificado en .pem para poder buscar la fecha de validez y el cif del emisor
            $certContent = file_get_contents($paths['cert']);
            if ($certContent === false) {
                throw new \Exception("No se pudo leer cert.pem");
            }

            // Devuelve en objeto el certificado
            $cert = openssl_x509_read($certContent);
            if ($cert === false) {
                throw new \Exception("No se pudo interpretar el certificado");
            }

            // Extraermos la información de la fecha de validez del certificado
            //La guardamos para luego subirla a la base de datos
            $certInfo = openssl_x509_parse($cert);
            if (!$certInfo || !isset($certInfo['validTo_time_t'])) {
                throw new \Exception("No se pudo obtener la fecha de validez del certificado");
            }

            $fechaValidez = date('Y-m-d', $certInfo['validTo_time_t']);

            //También extraemos el cif del emisor, para comprobar si es el mismo que el correspondiente
            $nifCertificado = $certInfo['subject']['serialNumber'] ?? null;

            if (!$nifCertificado) {
                throw new \Exception("El certificado no contiene un NIF/CIF válido");
            }

            // Limpiar prefijo que tiene al principio para que solo quede el cif y se pueda comparar bien
            // ej. "IDCES-12345678L" -> "12345678L"
            $nifCertificadoLimpio = preg_replace('/^[A-Z\-]+/', '', $nifCertificado);

            // Aquí comparamos NIF/CIF recibido con el del certificado
            // Lo ponemos todo en mayúsculas y le quitamos los espacios innecesarios
            if (strtoupper(trim($nifCertificadoLimpio)) !== strtoupper(trim($data['cif']))) {
                throw new \Exception("El NIF no coincide con el del certificado digital");
            }

            // Comprobamos la fecha actual
            // Comparamos la fecha actual con la fecha de validez del certificado para saber si está caducado o no, si está caducada no se subirá el certificado digital y saldrá un error
            // También comprobamos que si le faltan menos de 20 días para caducarse, entonces lo aceptará con un mensaje avisando de que está a punto de caducar
            //Si le faltan mas de 20 días, entonces se aceptará sin ningún problema
            $hoy = new DateTime();
            $fechaExpira = new \DateTime($fechaValidez);
            $diasRestantes = (int)$hoy->diff($fechaExpira)->format('%r%a');

            if ($diasRestantes < 0) {
                throw new \Exception("El certificado ya ha caducado el $fechaValidez");
            }

            Emisores::updateOrCreate(
                ['cif' => $data['cif']],
                [
                    'certificado'          => $data['certificado'],
                    'password'             => $data['password'],
                    'correoAdministrativo' => $data['correoAdministrativo'],
                    'nombreEmpresa'        => $data['nombreEmpresa'],
                    'fechaValidez'         => $fechaValidez
                ]
            );

            if ($diasRestantes <= 20) {
                return response()->json([
                    'validado' => true,
                    'fechaValidez' => $fechaValidez,
                    'mensaje' => "El certificado caduca en menos de 20 días"
                ]);
            }

            return response()->json([
                'validado' => true,
                'fechaValidez' => $fechaValidez
            ]);

            //Ejemplo uso del método encriptar string(Contraseña)
            //$contrasenna = $desencriptador->encryptString("Verifactu");
        } catch (\Throwable $e) {
            //Si hay algún error(cif incorrecto o fecha validez caducada) entonces no se pasará el certificado digital
            return response()->json([
                'validado' => false,
                'fechaValidez' => $fechaValidez,
                'mensaje' => $e->getMessage()
            ]);
        }
    }
}
