<?php

namespace App\Http\Controllers;

use App\Models\Emisores;
use Illuminate\Http\Request;
use App\Services\Encriptar;
use DateTime;
use Illuminate\Support\Facades\DB;


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
            $cifCertificado = null;

            //Buscamos en el primer campo si está el cif del emisor
            if (isset($certInfo['subject']['CN'])) {
                $cn = $certInfo['subject']['CN'];
                // Extraer el CIF dentro del paréntesis después de "R:"
                if (preg_match('/\(R:\s*([0-9A-Z]+)\)/', $cn, $matches)) {
                    $cifCertificado = $matches[1];
                }
            }

            //Si no está, buscamos en el segundo campo
            if (!$cifCertificado && isset($certInfo['subject']['2.5.4.97'])) {
                $cifCertificado = preg_replace('/^VATES-/', '', $certInfo['subject']['2.5.4.97']);
            }

            //Si no funciona ninguna de las anteriores, lo extraemos desde serialNumber
            if (!$cifCertificado && isset($certInfo['subject']['serialNumber'])) {
                $cifCertificado = preg_replace('/^[A-Z\-]+/', '', $certInfo['subject']['serialNumber']);
            }

            if (!$cifCertificado) {
                throw new \Exception("No se pudo extraer el CIF del certificado");
            }

            // Comparar con el CIF enviado
            if (strtoupper($data['cif']) !== strtoupper($cifCertificado)) {
                throw new \Exception("El CIF enviado ({$data['cif']}) no coincide con el del certificado ({$cifCertificado})");
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

    public function notificacion(Request $request)
    {
        $hoy = new DateTime();
        $emisores = Emisores::all();

        $resultado = [];

        foreach ($emisores as $emisor) {
            if (!$emisor->fechaValidez) {
                $resultado[] = [
                    'cif' => $emisor->cif,
                    'nombreEmpresa' => $emisor->nombreEmpresa,
                    'mensaje' => "No tiene fecha de validez registrada"
                ];
                continue;
            }

            $fechaExpira = new \DateTime($emisor->fechaValidez);
            $diasRestantes = (int)$hoy->diff($fechaExpira)->format('%r%a');

            if ($diasRestantes < 0) {
                $estado = "Caducado hace" . abs($diasRestantes) . "dias.";
            } elseif ($diasRestantes <= 10) {
                $estado = "Caduca en menos de 10 días";
            } elseif ($diasRestantes <= 20) {
                $estado = "Caduca en menos de 20 días";
            } elseif ($diasRestantes <= 30) {
                $estado = "Caduca en menos de 30 días";
            } else {
                $estado = "Válido (" . $diasRestantes . " días restantes)";
            }

            $resultado[] = [
                'cif' => $emisor->cif,
                'nombreEmpresa' => $emisor->nombreEmpresa,
                'fechaValidez' => $emisor->fechaValidez,
                'diasRestantes' => $diasRestantes,
                'estado' => $estado
            ];
        }
        return response()->json($resultado);
    }
}
