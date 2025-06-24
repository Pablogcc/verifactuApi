<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClienteSOAPConsultaCDI;

class ConsultaCDIController extends Controller
{

    public function validatedni(Request $request, $numSerieFactura) {
        $factura = DB::connection('pruebadb')->table('facturas')
        ->where('numSerieFactura', $numSerieFactura)->first();

       if (!$factura) {
        return response()->json([
            'success' => false,
            'message' => "Ninguna factura encontrada"
        ], 404);
       }

       $nif = strtoupper($factura->nif);
       $nombreRazonEmisor = strtoupper($factura->nombreRazonEmisor);


        $clienteCDI = new ClienteSOAPConsultaCDI();
        $respuesta = $clienteCDI->consultar($nif, $nombreRazonEmisor);

        return response()->json([
            'success' => true,
            'message' => "Respuesta de la AEAT",
            'data' => $respuesta
        ]);
    }
}
