<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesSOAPConsultaCDI;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ConsultaCDIController extends Controller
{



    public function validate(Request $request)
    {
        //Recogemos el nif y el nombre por el body(los dos son requeridos)
        $data = $request->validate([
            'nif' => 'required|string',
            'nombre' => 'required|string'
        ]);

        //Cogemos el NIF y el NOMBRE de esa factura elegida y la ponemos en mayúsculas
        $nif = strtoupper($data['nif']);
        $nombre = strtoupper($data['nombre']);

        // Llamamos al servicio y comprobamos si el NIF y el NOMBRE son correctos
        $clienteCDI = new ClientesSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($nif, $nombre);

        //Verificamos si está 'IDENTIFICADO' o 'NO IDENTIFICADO'
        $esCorrecto = false;

        try {
            //Miramos en la etiqueta donde aparece si está identificado o no(VNifV2Sal:Resultado)
            $xml = simplexml_load_string($respuesta);
            //Creamos los prefijos de cada url para la sigueinte petición xpath
            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('VNifV2Sal', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd');
                //Extraemos el nodo de 'VNifV2Sal:Resultado'
            $result = $xml->xpath('//VNifV2Sal:Resultado');

            //Verificamos de que existe y de que pone 'IDENTIFICADO'
            if (!empty($result) && strtoupper((string)$result[0]) == 'IDENTIFICADO') {
                $esCorrecto = true;
            }
        } catch (\Exception $e) {
            //Si hay algún problema, ponemos que no cargó bien la respuesta de la API de la AEAT y un error 500
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la respuesta de la AEAT',
                'error' => $e->getMessage()
            ], 500);
        }

        //Si está identificado creamos un token en el jsonm si no, no se crea el token
        if ($esCorrecto) {
            $token = Str::random(32);
            //Enviamos el token por el body y por el header
            return response()->json([
                'success' => true,
                'message' => 'Comprobación de la AEAT: ',
                'token' => $token,
                'data' => $respuesta
            ])->header('Authorization', $token);
        } else {
            //No enviamos el token por ningún lado
            return response()->json([
                'success' => false,
                'message' => 'Comprobación de la AEAT',
                'data' => $respuesta
            ], 401);
        }
    }
}
