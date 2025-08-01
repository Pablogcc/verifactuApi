<?php

namespace App\Http\Controllers;

use App\Services\DateTimeServer;
use Illuminate\Http\Request;

class FechaHoraServerController extends Controller
{
    public function fechaHoraZonaHoraria(Request $request) {
        
        $token = $request->query('token');

        $servicio = new DateTimeServer();
        $datos = $servicio->dateTimeService();

        if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return $datos;
        } else {
            return response()->json("Token incorrecto");
        }
    }
}
