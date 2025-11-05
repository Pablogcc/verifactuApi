<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesSOAPConsultaCDI;
use App\Services\ClientesSOAPConsultaVIES;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ConsultaCDIController extends Controller
{
    public function validarDNI(Request $request)
    {
        //Recogemos el nif, el nombre y el token por el body(los tres son requeridos)
        //Ponemos un mensaje si el token no es válido
        $message = [
            'token.in' => 'El token no es válido.',
        ];

        //Aquí es donde recogemos los campos
        // idTypeNum = 01 -> DNI nacional
        // idTypeNum = 02 -> DNI intracomunitario
        // idTypeNum = 03 -> DNI extranjero
        $data = $request->validate([
            'nif' => 'required|string',
            'nombre' => 'required|string',
            'idTypeNum' => 'nullable|string|in:01,02,03',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx'],
        ], $message);

        //Si el idTypeNum está vacío o no tiene etiqueta, se pondrá por defecto "01"
        $idTypeNum = $data['idTypeNum'] ?? '01';

        // Si es intracomunitario -> usar VIES
        if ($idTypeNum === '02') {
            $nombre = strtoupper($data['nombre']);
            $nifInput = strtoupper(trim($data['nif'])); // puede venir "ESB54027545" o "ES B54027545"
            // Normalizar: quitar espacios y guiones
            $nifClean = preg_replace('/[\s\-]+/', '', $nifInput);

            // Sacar countryCode y vatNumber
            $countryCode = strtoupper(substr($nifClean, 0, 2));
            $vatNumber = substr($nifClean, 2);

            // Validación mínima
            if (!ctype_alpha($countryCode) || $vatNumber === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato VAT inválido.',
                ], 400);
            }

            // Llamamos al servicio VIES
            $clienteVies = new ClientesSOAPConsultaVIES();
            // Le pasamos la cadena con prefijo (el servicio la normaliza internamente)
            $viesResponse = $clienteVies->consultar($countryCode . $vatNumber);

            // Si el servicio devolvió un error (array), lo devolvemos
            if (is_array($viesResponse) && isset($viesResponse['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error VIES: ' . ($viesResponse['error'] ?? 'unknown'),
                    'details' => $viesResponse,
                ], 500);
            }

            // Parseamos la respuesta SOAP (string XML)
            $valid = false;
            $messageText = 'Comprobación VIES';

            if (!empty($viesResponse) && is_string($viesResponse)) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($viesResponse);
                if ($xml !== false) {
                    // Registramos namespace para VIES
                    $xml->registerXPathNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
                    $xml->registerXPathNamespace('vies', 'urn:ec.europa.eu:taxud:vies:services:checkVat:types');

                    // 1) Comprobar si viene un SOAP Fault con MS_MAX_CONCURRENT_REQ
                    $faultNodes = $xml->xpath('//env:Fault/faultstring');
                    if (!empty($faultNodes)) {
                        $faultString = trim((string) $faultNodes[0]);
                        if (stripos($faultString, 'MS_MAX_CONCURRENT_REQ') !== false) {
                            // Según tu petición: tratamos este fallo como "bien"
                            return response()->json([
                                'success' => true,
                                'message' => $nombre
                            ]);
                        }
                    }

                    // 2) Si no hay fault, intentamos leer <valid>
                    $validNode = $xml->xpath('//vies:valid');

                    if (!empty($validNode)) {
                        $validText = trim((string) $validNode[0]);
                        $valid = ($validText === 'true' || $validText === '1');
                    }

                    $messageText = $valid ? 'VAT válido' : 'VAT no válido';
                } else {
                    // error parseando XML
                    //$errors = libxml_get_errors();
                    libxml_clear_errors();
                    return response()->json([
                        'success' => false,
                        'message' => 'Respuesta VIES no válida (XML corrupto).',
                        'raw' => $viesResponse
                    ], 500);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Respuesta vacía de VIES.',
                ], 500);
            }

            // Si VIES indica no válido, devolvemos NO IDENTIFICADO; si válido, devolvemos el nombre
            if ($valid === false) {
                return response()->json([
                    'success' => $valid,
                    'message' => "NIF intracomunitario no válido"
                ]);
            } else {
                return response()->json([
                    'success' => $valid,
                    'message' => $nombre
                ]);
            }
        }

        if ($idTypeNum === '03') {
            return response()->json([
                'success' => true,
                'message' => 'NIF extrajero correcto'
            ]);
        }

        // ========= Resto: NIF nacional (01) =========
        //Cogemos el NIF y el NOMBRE de esa factura elegida y la ponemos en mayúsculas
        $nif = strtoupper($data['nif']);
        $nombre = strtoupper($data['nombre']);

        // Llamamos al servicio y comprobamos si el NIF y el NOMBRE son correctos
        $clienteCDI = new ClientesSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($nif, $nombre);

        $success = false;
        $messageText = 'Comprobación de la AEAT';
        // Si tenemos respuesta, procesamos el XML para ver si está "IDENTIFICADO" o "NO IDENTIFICADO"
        if ($respuesta) {
            //Registramos los namespaces y buscamos la etiqueta Resultado dentro del XML
            $xml = simplexml_load_string($respuesta);
            $xml->registerXPathNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('res', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd');

            $nodo = $xml->xpath('//res:Resultado');
            $nombreNodo = $xml->xpath('//res:Nombre');

            //Si está, entonces comprobamos si pone "IDENTIFICADO"
            if (!empty($nodo)) {
                $resultado = trim((string) $nodo[0]);

                if ($resultado === 'IDENTIFICADO') {
                    $success = true;

                    if (!empty($nombreNodo)) {
                        $nombreReal = trim((string) $nombreNodo[0]);
                        $messageText = $nombreReal;
                    }
                } else {
                    $messageText = $resultado;
                }
            }
        }

        //Enviamos el token por el body, si está identificado, en el success pondremos "true" y en el message mostramos el nombre
        return response()->json([
            'success' => $success,
            'message' => $messageText
        ]);
    }
}
