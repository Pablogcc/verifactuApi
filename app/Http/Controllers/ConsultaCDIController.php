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

        //Devolvemos la respuesta en un json
        /*return response()->json([
            'success' => true,
            'message' => "Respuesta de la AEAT",
            'token' => Str::random(40),
            'data' => $respuesta
        ]);*/

        $esCorrecto = false;

        try {
            $xml = simplexml_load_string($respuesta);

            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('VNifV2Sal', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd');

            $result = $xml->xpath('//VNifV2Sal:Resultado');

            if (!empty($result) && strtoupper((string)$result[0] === 'IDENTIFICADO')) {
                $esCorrecto = true;
            }
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la respuesta de la AEAT',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($esCorrecto) {
            $token = Str::random(32);

            return response()->json([
                'success' => true,
                'message' => 'Respuesta de la AEAT',
                'token' => $token,
                'data' => $respuesta
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Respuesta de la AEAT',
                'data' => $respuesta
            ], 401);
        }
    }
}
