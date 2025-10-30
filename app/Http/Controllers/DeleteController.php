<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\ClientesSOAPVerifactu;
use App\Services\FacturaXmlGenerator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeleteController extends Controller
{

    public function verifactuPrueba(Request $request)
    {
        //Añadimos un token a la url para la petición get
        $token = $request->query('token');

        //Llamamos al servicio del certificado digital para luego enviarselo a la api
        $verifactuService = new ClientesSOAPVerifactu();

        //Guardamos el total de facturas que se han guardado
        $totalFacturas = 0;
        $totalTiempo = 0;

        //Almacenamos las facturas que su estado_proceso esté desbloqueada y su estado_registro esté sin presentar a la AEAT
        $facturas = Facturas::where('estado_proceso', 0)
            ->where('estado_registro', 0)->get();

        //Vamos procesando facturas una a una
        foreach ($facturas as $factura) {
            //Guardamos el tiempo que tarda una factura en generarse y mandarse a la API
            $inicio = microtime(true);

            try {
                //Almacenamos los datos del numero de serie, el numero de la factura, la fecha(ejercicio) y el cif del emisor
                $numero = $factura->numFactura;
                $serie = $factura->serie;
                $fechaEjercicio = $factura->ejercicio;
                $cifEmisor = $factura->idEmisorFactura;

                //Filtramos los datos de la factura anterior, para luego pornerlos en los campos de la huella actual y poder calcular la huella
                //Si es la primera factura de la serie, se ponen los mismos datos que la misma
                if ($numero > 1) {
                    $numFacturaAnterior = $numero - 1;

                    // Buscamos la primera factura anterior con misma serie pero con el número de factura anterior
                    $facturaAnterior = Facturas::where('serie', $serie)
                        ->where('numFactura', $numFacturaAnterior)
                        ->where('ejercicio', $fechaEjercicio)
                        ->where('idEmisorFactura', $cifEmisor)
                        ->first();

                    //Si tenemos la factura anterior, entonces se rellenan los campos de la factura anterior en los campos de la factura actual
                    if ($facturaAnterior) {
                        $factura->IDEmisorFacturaAnterior = $facturaAnterior->idEmisorFactura;
                        $factura->numSerieFacturaAnterior = $facturaAnterior->numSerieFactura;
                        $factura->FechaExpedicionFacturaAnterior = $facturaAnterior->fechaExpedicionFactura;
                        $factura->huellaAnterior = $facturaAnterior->huella;
                    } else {
                        // No encontrada: deja vacíos o nulos
                        $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                        $factura->numSerieFacturaAnterior = $factura->numSerieFactura;
                        $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                        $factura->huellaAnterior = $factura->huella;
                    }
                } else {
                    // Primera factura: no tiene anterior
                    $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                    $factura->numSerieFacturaAnterior = $factura->serie . '/0000000';
                    $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                    $factura->huellaAnterior = $factura->huella;
                }

                // Generar y guardar XML(Storage/facturas/...)
                $xml = (new FacturaXmlGenerator())->generateXml($factura);
                $carpetaOrigen = storage_path('facturas');
                $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($ruta, $xml);

                } catch (\Throwable $e) {
                // Error general
                //Si hay algún tipo de error en el servidor o interno, la factura se queda bloqueada y se guarda
                $factura->estado_proceso = 1;
                $factura->estado_registro = 0;
                $factura->error = $e->getMessage();
                $factura->save();
            }
        }

        // Guardar logs
        //Aquí guardamos todas las facturas y calculamos su media de tiempo que han tardado generarse en ese minuto, 
        if ($totalFacturas > 0) {
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => $mediaTiempo,
                'periodo' => now()->startOfMinute(),
                'tipo_factura' => 'desbloqueadas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        //Comprobamos que el token está puesto en la url, si no, da error al enviar la petición
        if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'success' => true,
                'message' => "Facturas generadas $totalFacturas",
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Token incorrecto"
            ]);
        }
    }

}
