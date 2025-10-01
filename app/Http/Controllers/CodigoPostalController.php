<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ciudades;

class CodigoPostalController extends Controller
{
    public function codigoPostal(Request $request)
    {
        //Recogemos el campo del código postal y tambien hay que pasar el token para que sea correcto
        $data = $request->validate([
            'postCode' => 'required|string',
            'token' => 'required|string'
        ]);

        //Comprobamos si el token es correcto o no, si no lo es, la respuesta JSON saldrá token incorrecto
        if ($data['token'] !== 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'resultado' => false,
                'mensaje' => 'Token incorrecto'
            ]);
        }

        //Buscamos si está ese código postal en la base de datos y los guardamos todos en una variable
        $codPostal = Ciudades::where('postCode', $data['postCode'])->get();

        //Ponemos lo que queremos que se muestre por pantalla de la base de datos, y también el código del país(ESP)
        $codPostal = $codPostal->map(function ($item) {
            return [
                'postCode' => $item->postCode,
                'ciudad' => $item->ciudad,
                'provincia' => $item->provincia,
                'countryCode' => 'ESP'
            ];
        });

        //Si existe código postal, mostramos todas las ciudades y la provincias con ese código postal
        if ($codPostal->isEmpty()) {
            return response()->json([
                'resultado' => false,
                'mensaje' => 'Código postal no encontrado'
            ]);
        }

        return response()->json([
            'resultado' => true,
            'coincidencias' => $codPostal
        ]);
    }
}
