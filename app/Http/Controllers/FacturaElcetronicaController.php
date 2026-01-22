<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\Encriptar;
use App\Models\Emisores;
use App\Services\FacturaXmlElectronica;
use App\Services\FirmaXmlGeneratorElectronica;

class FacturaElcetronicaController extends Controller
{
    public function facturaElectronica(Request $request)
    {
        $rules = [
            'token'   => 'required|string',
            'firmada' => 'nullable|integer|in:1,0',

            'factura' => 'required|array',

            'factura.NumSerieFactura' => 'required|string',
            'factura.FechaExpedicionFactura' => 'required|string',
            'factura.IdEmisorFactura' => 'required|string',
            'factura.CifEmisor' => 'required|string',
            'factura.NombreEmisor' => 'required|string',
            'factura.NifCliente' => 'required|string',
            'factura.NombreCliente' => 'required|string',
            'factura.FechaOperacion' => 'required|string',
            'factura.DescripcionOperacion' => 'required|string',
            'factura.Serie' => 'required|string',
            'factura.NumFactura' => 'required|integer',
            'factura.Notas' => 'nullable|string',

            'factura.EmisorDirec' => 'required|string',
            'factura.EmisorCpostal' => 'required|string',
            'factura.EmisorCiudad' => 'required|string',
            'factura.EmisorProv' => 'required|string',
            'factura.EmisorCpais' => 'required|string',

            'factura.ReceptorDirec' => 'required|string',
            'factura.ReceptorCpostal' => 'required|string',
            'factura.ReceptorCiudad' => 'required|string',
            'factura.ReceptorProv' => 'required|string',
            'factura.ReceptorCpais' => 'required|string',

            'factura.TotalImporteBruto' => 'required|numeric',
            'factura.TotalDescuentosGenerales' => 'required|numeric',
            'factura.TotalRecargosGenerales' => 'required|numeric',
            'factura.TotalBaseImponible' => 'required|numeric',
            'factura.TotalImpuestosRepercutidos' => 'required|numeric',
            'factura.TotalImpuestosRetenidos' => 'required|numeric',
            'factura.TotalFactura' => 'required|numeric',
            'factura.TotalPendienteCobro' => 'required|numeric',

            'factura.Oficontable' => 'nullable|string',
            'factura.Orggestor' => 'nullable|string',
            'factura.Utramitadora' => 'nullable|string',
            'factura.OficontableDirec' => 'nullable|string',
            'factura.OficontableCpostal' => 'nullable|string',
            'factura.OficontableCiudad' => 'nullable|string',
            'factura.OficontableProv' => 'nullable|string',
            'factura.OficontableCpais' => 'nullable|string',
            'factura.OrggestorDirec' => 'nullable|string',
            'factura.OrggestorCpostal' => 'nullable|string',
            'factura.OrggestorCiudad' => 'nullable|string',
            'factura.OrggestorProv' => 'nullable|string',
            'factura.OrggestorCpais' => 'nullable|string',
            'factura.UtramitadoraDirec' => 'nullable|string',
            'factura.UtramitadoraCpostal' => 'nullable|string',
            'factura.UtramitadoraCiudad' => 'nullable|string',
            'factura.UtramitadoraProv' => 'nullable|string',
            'factura.UtramitadoraCpais' => 'nullable|string',

            'factura.Lineas' => 'nullable|array',
            'factura.Lineas.*.Descripcion' => 'required|string',
            'factura.Lineas.*.Cantidad' => 'required|numeric',
            'factura.Lineas.*.UnidadMedida' => 'required|string',
            'factura.Lineas.*.PrecioUnitarioSinIva' => 'required|numeric',
            'factura.Lineas.*.BaseImponible' => 'required|numeric',
            'factura.Lineas.*.TipoIva' => 'required|numeric',
            'factura.Lineas.*.CuotaIva' => 'required|numeric',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'Faltan campos obligatorios'
            ], 422);
        }

        $data = $validator->validated();

        if ($data['token'] !== 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'Token incorrecto',
            ]);
        }

        $firmada = $data['firmada'] ?? 1;
        $factura = $data['factura'];
        $lineas  = $factura['Lineas'] ?? [];

        if (empty($lineas)) {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'No hay líneas en la factura',
            ], 422);
        }

        $emisor = Emisores::where('cif', $factura['CifEmisor'])->first();
        if (!$emisor) {
            return response()->json(['mensaje' => 'Emisor no encontrado']);
        }

        $desencriptador = new Encriptar();
        $passwordCert = $desencriptador->decryptString($emisor->password);
        $desencriptador->decryptBase64AndDownloadPfx($passwordCert, $emisor->certificado, $emisor->cif);

        $xml = (new FacturaXmlElectronica())->generateXml($factura, $lineas, $firmada === 1);

        if ($firmada === 1) {
            $xmlFirmado = (new FirmaXmlGeneratorElectronica())->firmaXml($xml, $emisor->cif, $passwordCert);
            $xmlParaGuardar = $xmlFirmado;
        } else {
            $xmlParaGuardar = $xml;
        }

        $fecha = $factura['FechaExpedicionFactura'] ?? $factura['FechaOperacion'] ?? date('Y-m-d');
        $ts    = strtotime($fecha) ?: time();
        $ejercicio = date('Y', $ts);
        $mes       = date('m', $ts);
        $cifEmisor = $factura['CifEmisor'];

        $carpetaRaiz      = storage_path('facturasElectronicas');
        $carpetaTipo      = $carpetaRaiz . ($firmada === 1 ? '/facturasFirmadas' : '/facturasSinFirmar');
        $carpetaCif       = $carpetaTipo . '/' . $cifEmisor;
        $carpetaEjercicio = $carpetaCif . '/' . $ejercicio;
        $carpetaMes       = $carpetaEjercicio . '/' . $mes;

        foreach ([$carpetaRaiz, $carpetaTipo, $carpetaCif, $carpetaEjercicio, $carpetaMes] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $nombreArchivo =
            'EJERCICIO-' . $ejercicio .
            '_MES-' . $mes .
            '_SERIE-' . $factura['Serie'] .
            '_NUM-' . $factura['NumFactura'] .
            '.xml';

        $ruta = $carpetaMes . '/' . $nombreArchivo;

        file_put_contents($ruta, $xmlParaGuardar);

        $xmlBase64  = base64_encode($xmlParaGuardar);
        $encriptado = $desencriptador->encryptBase64InputReturnBase64($xmlBase64);

        return response()->json([
            'resultado' => true,
            'factura'   => $encriptado,
        ]);
    }
}
