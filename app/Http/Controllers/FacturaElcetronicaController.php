<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\Encriptar;
use App\Models\Emisores;
use App\Services\FacturaXmlElectronica;
use App\Services\FirmaXmlGeneratorElectronica;
use App\Services\ChilkatLikeFacturaeSigner;

/**
 * Controlador para la generación de facturas electrónicas en formato Facturae.
 *
 * Valida la petición de entrada, comprueba el token de seguridad,
 * localiza el emisor y su certificado, genera el XML Facturae
 * y opcionalmente lo firma antes de guardarlo y devolverlo encriptado.
 */
class FacturaElcetronicaController extends Controller
{
    /**
     * Genera una factura electrónica a partir de los datos recibidos en la petición.
     *
     * Flujo general:
     *  - Valida la estructura del JSON (cabecera, totales y líneas).
     *  - Comprueba que el token sea válido.
     *  - Busca el emisor y recupera la información del certificado.
     *  - Genera el XML Facturae con los datos de la factura.
     *  - Firma el XML si se indica que la factura debe ir firmada.
     *  - Guarda el XML en disco y devuelve el resultado encriptado.
     */
    public function facturaElectronica(Request $request)
    {
        // Reglas de validación de todos los campos esperados en la factura.
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

            // Periodo de facturación (opcional)
            'factura.InicioPeriodo' => 'nullable|string',
            'factura.FinPeriodo' => 'nullable|string',
            'factura.Inicioperiodo' => 'nullable|string',
            'factura.Finperiodo' => 'nullable|string',

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

        // Construimos el validador con las reglas definidas.
        $validator = Validator::make($request->all(), $rules);

        // Si la validación falla, se devuelve un error indicando que faltan campos obligatorios.
        if ($validator->fails()) {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'Faltan campos obligatorios'
            ], 422);
        }

        // Datos ya saneados según las reglas de validación.
        $data = $validator->validated();

        // Comprobación del token de seguridad para controlar el acceso a este endpoint.
        if ($data['token'] !== 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'Token incorrecto',
            ]);
        }

        // Parámetros principales: indicador de firma, cabecera de la factura y líneas de detalle.
        $firmada = $data['firmada'] ?? 1;
        $factura = $data['factura'];
        $lineas  = $factura['Lineas'] ?? [];

        // La factura debe contener al menos una línea de detalle.
        if (empty($lineas)) {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'No hay líneas en la factura',
            ], 422);
        }

        // Localizamos el emisor en base al CIF indicado en los datos de la factura.
        $emisor = Emisores::where('cif', $factura['CifEmisor'])->first();
        if (!$emisor) {
            return response()->json(['mensaje' => 'Emisor no encontrado']);
        }

        // Comprobamos que exista el archivo .pfx asociado al emisor en storage/certs/{cif}/.
        $pfxPath = storage_path('certs/' . $emisor->cif . '/certificado.pfx');
        if (!file_exists($pfxPath)) {
            return response()->json([
                'resultado' => false,
                'mensaje'   => 'Faltan datos adicionales del certificado (archivo .pfx no encontrado)',
            ], 422);
        }

        // Servicio de encriptación para recuperar la contraseña real del certificado del emisor.
        $desencriptador = new Encriptar();
        $passwordCert = $desencriptador->decryptString($emisor->password);

        //$desencriptador->decryptBase64AndDownloadPfx($passwordCert, $emisor->certificado, $emisor->cif);

        // Generamos el XML Facturae a partir de los datos validados de la factura y sus líneas.
        $xml = (new FacturaXmlElectronica())->generateXml($factura, $lineas, $firmada === 1);

        // Si la factura debe ir firmada, se firma el XML con la política de firma establecida.
        if ($firmada === 1) {
            $xmlFirmado = (new ChilkatLikeFacturaeSigner())->signFacturaeWithPolicy(
                $xml,
                $pfxPath,
                $passwordCert,
                true
            );
            $xmlParaGuardar = $xmlFirmado;
        } else {
            $xmlParaGuardar = $xml;
        }

        // A partir de la fecha de la factura se determinan el ejercicio (año) y el mes.
        $fecha = $factura['FechaExpedicionFactura'] ?? $factura['FechaOperacion'] ?? date('Y-m-d');
        $ts    = strtotime($fecha) ?: time();
        $ejercicio = date('Y', $ts);
        $mes       = date('m', $ts);
        $cifEmisor = $factura['CifEmisor'];

        // Construimos la estructura de carpetas donde se almacenará el XML.
        $carpetaRaiz      = storage_path('facturasElectronicas');
        $carpetaTipo      = $carpetaRaiz . ($firmada === 1 ? '/facturasFirmadas' : '/facturasSinFirmar');
        $carpetaCif       = $carpetaTipo . '/' . $cifEmisor;
        $carpetaEjercicio = $carpetaCif . '/' . $ejercicio;
        $carpetaMes       = $carpetaEjercicio . '/' . $mes;

        // Nos aseguramos de que toda la ruta exista, creando las carpetas necesarias si no están.
        foreach ([$carpetaRaiz, $carpetaTipo, $carpetaCif, $carpetaEjercicio, $carpetaMes] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Nombre del archivo que identifica ejercicio, mes, serie y número de factura.
        $nombreArchivo =
            'EJERCICIO-' . $ejercicio .
            '_MES-' . $mes .
            '_SERIE-' . $factura['Serie'] .
            '_NUM-' . $factura['NumFactura'] .
            '.xml';

        $ruta = $carpetaMes . '/' . $nombreArchivo;

        // Guardamos el XML (firmado o sin firmar) en disco en la ruta calculada.
        file_put_contents($ruta, $xmlParaGuardar);

        // Codificamos el XML en base64 y posteriormente lo encriptamos antes de devolverlo.
        $xmlBase64  = base64_encode($xmlParaGuardar);
        $encriptado = $desencriptador->encryptBase64InputReturnBase64($xmlBase64);

        // Respuesta final: indica éxito y entrega la factura encriptada al cliente.
        return response()->json([
            'resultado' => true,
            'factura'   => $encriptado,
        ]);
    }
}
 