<?php

namespace App\Http\Controllers;

use App\Models\Emisores;
use Illuminate\Http\Request;
use App\Services\Encriptar;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


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

            // Generar y guardar un .pfx en la misma carpeta que los .pem
            if (isset($paths['key']) && file_exists($paths['key'])) {
                $keyContent = file_get_contents($paths['key']);
                if ($keyContent === false) {
                    throw new \Exception("No se pudo leer key.pem");
                }

                $privateKey = openssl_pkey_get_private($keyContent);
                if ($privateKey === false) {
                    throw new \Exception("La clave privada (key.pem) no es válida");
                }

                $pfx = null;
                $exportOk = openssl_pkcs12_export($cert, $pfx, $privateKey, $contrasenna);

                if (!$exportOk || !$pfx) {
                    throw new \Exception("No se ha podido generar el archivo .pfx");
                }

                $pfxPath = dirname($paths['cert']) . DIRECTORY_SEPARATOR . 'certificado.pfx';
                if (file_put_contents($pfxPath, $pfx) === false) {
                    throw new \Exception("No se ha podido guardar el archivo .pfx");
                }
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

    public function comprobacionEstado(Request $request)
    {

        //Recibo por el body el cif del emisor para recoger su certificado
        $data = $request->validate([
            'cif' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);

        //Recogemos todos los campos del cif recibido por el body
        $emisor = Emisores::where('cif', $data['cif'])->first();

        //Comprobamos que el emisor está registrado en la base de datos
        if (!$emisor) {
            return response()->json([
                'resultado' => false,
                'mensaje' => "El cif del emisor no tiene certificado digital"
            ]);
        }

        //Llamamos al método de desencriptar, para desencriptar el correo administrativo y el nombre de la empresa
        $desencriptador = new Encriptar();
        //Los guardamos en valores para luego utilizarlos
        $correo = $desencriptador->decryptString($emisor->correoAdministrativo);
        $empresa = $desencriptador->decryptString($emisor->nombreEmpresa);

        return response()->json([
            'resultado' => true,
            "cif" => $emisor->cif,
            'fechaVlidez' => $emisor->fechaValidez,
            'correoAdministrativo' => $correo,
            'nombreEmpresa' => $empresa
        ]);
    }

    /**
     * Genera un archivo .pfx a partir de los archivos cert.pem y key.pem
     * almacenados en storage/certs/{cif} y devuelve el .pfx en Base64 junto
     * con la contraseña generada.
     */
    public function generarPfxDesdePem(Request $request)
    {
        $data = $request->validate([
            'cif' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ]);

        $cif = $data['cif'];

        try {
            $basePath = storage_path('certs/' . $cif . '/');
            $certPath = $basePath . 'cert.pem';
            $keyPath = $basePath . 'key.pem';

            if (!file_exists($certPath) || !file_exists($keyPath)) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "No se han encontrado cert.pem o key.pem para el CIF indicado"
                ], 404);
            }

            $certContent = file_get_contents($certPath);
            $keyContent = file_get_contents($keyPath);

            if ($certContent === false || $keyContent === false) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "No se han podido leer los archivos cert.pem o key.pem"
                ], 500);
            }

            $cert = openssl_x509_read($certContent);
            $privateKey = openssl_pkey_get_private($keyContent);

            if ($cert === false || $privateKey === false) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "Los archivos de certificado o clave privada no son válidos"
                ], 500);
            }

            $password = bin2hex(random_bytes(8));

            $pfx = null;
            $exportOk = openssl_pkcs12_export($cert, $pfx, $privateKey, $password);

            if (!$exportOk || !$pfx) {
                return response()->json([
                    'resultado' => false,
                    'mensaje' => "No se ha podido generar el archivo .pfx"
                ], 500);
            }

            // Opcionalmente guardamos el archivo .pfx generado en disco
            $pfxPath = $basePath . 'certificado_generado.pfx';
            file_put_contents($pfxPath, $pfx);

            return response()->json([
                'resultado' => true,
                'cif' => $cif,
                'password' => $password,
                'pfxBase64' => base64_encode($pfx),
                'rutaPfx' => $pfxPath
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'resultado' => false,
                'mensaje' => "Error al generar el archivo .pfx: " . $e->getMessage()
            ], 500);
        }
    }

    public function notificacion(Request $request)
    {

        //Añadimos un token a la url para la petición
        // Y comprobamos que el token es correcto, si no, nos devuelve un JSON de respuesta de token incorrecto
        $token = $request->query('token');

        if ($token != 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'mensaje' => 'Token incorrecto'
            ]);
        }
        // Lo primero es guardar en variables la fecha actual para saber si a los certificados digitales les quedan menos de 30 días.
        // Luego buscamos los emisores que sus certificados les falten menos de 30 días. 
        $hoy = new DateTime();
        $fechaAviso = (clone $hoy)->modify('+30 days')->format('Y-m-d');
        $emisores = Emisores::whereDate('fechaValidez', '<=', $fechaAviso)->get();

        $resultado = [];

        // Miramos los emisores uno a uno
        foreach ($emisores as $emisor) {
            if (!$emisor->fechaValidez) {
                $resultado[] = [
                    'cif' => $emisor->cif,
                    'nombreEmpresa' => $emisor->nombreEmpresa,
                    'mensaje' => "No tiene fecha de validez registrada"
                ];
                continue;
            }

            // Se llama al método de desencriptar, para poder desencriptar el correo administrativo de la empresa y el nombre de la empresa para luego poder enviar el correo, y lo guardamos en variables
            $desencriptador = new Encriptar();
            $correo = $desencriptador->decryptString($emisor->correoAdministrativo);
            $empresa = $desencriptador->decryptString($emisor->nombreEmpresa);

            // Calculamos los días restantes que le quedan a cada certificado para que se caduque
            $fechaExpira = new \DateTime($emisor->fechaValidez);
            $diasRestantes = (int)$hoy->diff($fechaExpira)->format('%r%a');

            //Se comprueba si caducó el certificado
            //Si no ha caducado aún, entonces comprobamos cuantos días le quedan
            if ($diasRestantes < 0) {
                $estado = "Caducado hace " . abs($diasRestantes) . " días.";
            } else {
                // Definimos los límites de aviso
                $avisos = [10, 20, 30];

                // Renderizamos en HTML del correo desde el blade y lo guardamos en una variable
                $html = view('emails.certificados_aviso', [
                    'cif' => $emisor->cif,
                    'empresa' => $empresa,
                    'fechaValidez' => $emisor->fechaValidez,
                    'diasRestantes' => $diasRestantes
                ])->render();

                // Revisamos en que rango entra
                // Ejecutamos la petición POST
                foreach ($avisos as $diasAviso) {
                    if ($diasRestantes <= $diasAviso) {
                        $estado = "Caduca en menos de {$diasAviso} días";

                        Http::post(env('MAIL_API_URL'), [
                            "token" => env('MAIL_API_TOKEN'),
                            "emailFrom" => env('MAIL_API_FROM'),
                            "nameFrom" => "Demo",
                            "emailTo" => 'alberto@sauberofimatica.com',
                            "nameTo" => $empresa,
                            "subject" => "Certificado a punto de caducar",
                            "text" =>  $html
                        ]);
                        break;
                    }
                }
            }

            // Guardamos la información del emisor en un array de salida
            // Y devolvemos un JSON de respuesta
            $resultado[] = [
                'cif' => $emisor->cif,
                'nombreEmpresa' => $empresa,
                'fechaValidez' => $emisor->fechaValidez,
                'diasRestantes' => $diasRestantes,
                'estado' => $estado
            ];
        }
        return response()->json($resultado);
    }
}
