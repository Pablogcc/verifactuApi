<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Poblaciones;
use App\Services\FormateoCiudad;

class CodigoPostalController extends Controller
{
    //Llamamos al servicio y lo guardamos
    protected $formatter;

    public function __function(FormateoCiudad $formatter)
    {
        $this->formatter = $formatter;
    }

    public function codigoPostal(Request $request)
    {
        //Recogemos el campo del código postal y tambien hay que pasar el token para que sea correcto
        $data = $request->validate([
            'cp' => 'required|string',
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
        $codPostal = Poblaciones::where('cp', $data['cp'])->get();

        //Ponemos lo que queremos que se muestre por pantalla de la base de datos, y también el código del país(ESP)
        $codPostal = $codPostal->map(function ($item) {
            return [
                'cp' => $item->cp,
                'poblacion' => $this->formatter->normalizar($item->poblacion),
                'provincia_nombre' => $item->provincia_nombre,
                'cpais' => 'ESP',
                'pais' => 'España'
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
