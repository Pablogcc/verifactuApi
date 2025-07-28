<?php

namespace App\Http\Controllers;

use App\Models\Emisores;
use App\Models\Facturas;
use Illuminate\Http\Request;
use App\Services\Encriptar;



class CertificadosController extends Controller
{
    public function convertir(Request $request)
    {
        //Recibo por el body el cif del emisor para recoger su certificado
        $data = $request->validate([
            'cif' => 'required|string'
        ]);

        //Recogemos todos los campos del cif recibido por el body
        $emisor = Emisores::where('cif', $data['cif'])->get();

      
        //return $emisor[0]['certificado'];

          try {
                    $desencriptador = new Encriptar();

                    $contrasenna = $desencriptador->decryptString($emisor[0]['password']);
                    

                    $desencriptador->decryptBase64AndDownloadPfx($contrasenna, $emisor[0]['certificado'], $emisor[0]['cif']);

            
                    ////Ejemplo uso del método encriptar string(Contraseña)
                    //$contrasenna = $desencriptador->encryptString("Verifactu");
                } catch (\Throwable $e) {
                    return $e->getMessage();
                }
    }
}
