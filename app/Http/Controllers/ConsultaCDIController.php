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

        $message = [
            'token.in' => 'El token no es válido.',
        ];

        $data = $request->validate([
            'nif' => 'required|string',
            'nombre' => 'required|string',
            'token' => ['required', 'string', 'in:sZQe4cxaEWeFBe3EPkeah0KqowVBLx']
        ], $message);


        //Cogemos el NIF y el NOMBRE de esa factura elegida y la ponemos en mayúsculas
        $nif = strtoupper($data['nif']);
        $nombre = strtoupper($data['nombre']);

        // Llamamos al servicio y comprobamos si el NIF y el NOMBRE son correctos
        $clienteCDI = new ClientesSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($nif, $nombre);

        //Enviamos el token por el body y por el header
        return response()->json([
            'success' => true,
            'message' => 'Comprobación de la AEAT: ',
            'token' => 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx',
            'data' => $respuesta
        ]);
    }
}
