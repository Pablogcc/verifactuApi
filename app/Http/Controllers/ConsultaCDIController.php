<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesSOAPConsultaCDI;

class ConsultaCDIController extends Controller
{



    public function validate(Request $request, $numSerieFactura)
    {

        $factura = DB::table('facturas')->where('numSerieFactura', $numSerieFactura)->first();

        if (!$factura) {
            return response()->json([
                'success' => false,
                'message' => 'Ninguna factura encontrada'
            ], 404);
        }

        $nif = strtoupper($factura->nif);
        $nombre = strtoupper($factura->nombre);

        // $nif = strtoupper('29527583E');
        // $nombre = strtoupper('GARCIA CELDRAN PABLO');

        $clienteCDI = new ClientesSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($nif, $nombre);

        return response()->json([
            'success' => true,
            'message' => "Respuesta de la AEAT",
            'data' => $respuesta
        ]);
    }
}
