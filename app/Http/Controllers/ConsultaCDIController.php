<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesSOAPConsultaCDI;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        $data = $request->validate([
            'nif' => 'required|string',
            'nombre' => 'required|string',
            'idTypeNum' => 'nullable|string|in:01,02,03',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ], $message);

        //Si el idTypeNum está vacío o no tiene etiqueta, se pondrá por defecto "01"
         $idTypeNum = $data['idTypeNum'] ?? '01';

         //Si el idTypeNum es "02" o "03", entonces será un nif intracomunitario o un nif extranjero 
         if ($idTypeNum === '02') {
            return response()->json([
                'success' => true,
                'message' => 'NIF intracomunitario correcto'
            ]);
        }

         if ($idTypeNum === '03') {
            return response()->json([
                'success' => true,
                'message' => 'NIF extrajero correcto'
            ]);
        }

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
