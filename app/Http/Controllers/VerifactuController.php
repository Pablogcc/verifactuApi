<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\ClientesSOAPVerifactu;
use App\Services\FacturaXmlGenerator;
use App\Services\AgrupadorFacturasXmlService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Throwable;

class VerifactuController extends Controller
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

        // Instancias necesarias (aquí se crean UNA sola vez)
        $xmlGenerator = new \App\Services\FacturaXmlGenerator();
        $agrupadorXmlService = new \App\Services\AgrupadorFacturasXmlService($xmlGenerator);

        //Almacenamos las facturas que su estado_proceso esté desbloqueada y su estado_registro esté sin presentar a la AEAT
        $facturas = Facturas::where('estado_proceso', 0)
            ->where('estado_registro', 0)
            ->orderBy('idEmisorFactura')
            ->orderBy('id')
            ->get();

        // Si no hay facturas, salir pronto (se mantiene el control de token al final)
        if ($facturas->isEmpty()) {
            if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay facturas para procesar.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Token incorrecto"
                ]);
            }
        }

        // Agrupar por emisor (idEmisorFactura) — un XML por emisor
        $porEmisor = $facturas->groupBy('idEmisorFactura');

        foreach ($porEmisor as $cifEmisor => $grupo) {
            // Antes de crear el XML agrupado, asegurarnos de rellenar encadenamiento/huella
            foreach ($grupo as $factura) {
                $numero = $factura->numFactura;
                $serie = $factura->serie;
                $fechaEjercicio = $factura->ejercicio;
                $currentId = $factura->id;

                // Buscamos la factura anterior del mismo emisor con id menor (la más cercana)
                $facturaAnterior = Facturas::where('idEmisorFactura', $cifEmisor)
                    ->where('id', '<', $currentId)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($facturaAnterior) {
                    $factura->IDEmisorFacturaAnterior = $facturaAnterior->idEmisorFactura;
                    $factura->numSerieFacturaAnterior = $facturaAnterior->numSerieFactura;
                    $factura->FechaExpedicionFacturaAnterior = $facturaAnterior->fechaExpedicionFactura;
                    $factura->huellaAnterior = $facturaAnterior->huella;
                } else {
                    // Igual que antes: si no hay anterior, se usan los datos de la misma factura
                    $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                    $factura->numSerieFacturaAnterior = $factura->numSerieFactura;
                    $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                    $factura->huellaAnterior = $factura->huella;
                }
            }

            $grupo = collect($grupo);

            $agrupables = $grupo->filter(function ($f) {
                return intval($f->modo_verifactu) === 1;
            })->values();
            $individuales = $grupo->filter(function ($f) {
                return intval($f->modo_verifactu) === 0;
            })->values();

            if ($agrupables->isNotEmpty()) {
                // Construir XML agrupado PARA ESTE EMISOR (incluye N sum:RegistroFactura)
                try {
                    $xmlAgrupado = $agrupadorXmlService->buildGroupedXml($agrupables);

                    $first = $agrupables->first();
                    $ejercicio = $first->ejercicio;
                    $mes = date('m');

                    $carpetaBase = storage_path('facturas');
                    $carpetaTipo = $carpetaBase . '/facturasVerifactu';
                    $carpetaCif = $carpetaTipo . '/' . $cifEmisor;
                    $carpetaEjercicio = $carpetaCif . '/' . $ejercicio;
                    $carpetaMes = $carpetaEjercicio . '/' . $mes;

                    foreach ([$carpetaBase, $carpetaTipo, $carpetaCif, $carpetaEjercicio, $carpetaMes] as $dir) {
                        if (!file_exists($dir)) {
                            @mkdir($dir, 0755, true);
                        }
                    }

                    $nombreArchivo = 'VERIFACTU_AGRUPADO'
                        . '_CIF-' . $cifEmisor
                        . '_EJERCICIO-' . $first->ejercicio
                        . '_MES-' . date('m')
                        . '_TOTAL-' . $agrupables->count()
                        . '_FACTURAS'
                        . '.xml';

                    $rutaAgrupado = $carpetaMes . '/' . $nombreArchivo;
                    file_put_contents($rutaAgrupado, $xmlAgrupado);
                } catch (\Throwable $e) {
                    // Si falla la creación del XML agrupado para este emisor, marcamos todas sus facturas con error y seguimos con el siguiente emisor
                    foreach ($agrupables as $f) {
                        $f->estado_proceso = 1;
                        $f->estado_registro = 0;
                        $f->error = 'Error creando XML agrupado: ' . $e->getMessage();
                        $f->save();
                    }
                    $xmlAgrupado = null;
                }

                // Enviar el XML agrupado UNA SOLA VEZ para este emisor y procesar la respuesta
                if (!empty($xmlAgrupado)) {
                    $inicioEnvio = microtime(true);
                    try {
                        // actualizar rutas (certificado) para el emisor
                        $verifactuService->actualizarRutas($cifEmisor);
                        $respuestaXml = $verifactuService->enviarFactura($xmlAgrupado);

                        // --- Nuevo parseo robusto para respuestas agrupadas (DOM + XPath) ---
                        libxml_use_internal_errors(true);
                        $dom = new \DOMDocument();

                        if (!@$dom->loadXML($respuestaXml)) {
                            // Si la respuesta no es XML, marcamos todas las facturas del grupo como error
                            foreach ($agrupables as $f) {
                                $f->estado_proceso = 1;
                                $f->estado_registro = 0;
                                $f->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                                $f->save();
                            }
                        } else {
                            $xpath = new \DOMXPath($dom);
                            // Registramos los namespaces típicos (si cambian, el fallback sin prefijo funcionará)
                            $xpath->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
                            $xpath->registerNamespace('tikR', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');
                            $xpath->registerNamespace('tik', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

                            // Buscamos todas las RespuestaLinea (con o sin prefijos)
                            $lineaNodes = $xpath->query('//tikR:RespuestaLinea | //tik:RespuestaLinea | //RespuestaLinea');

                            $acceptedCount = 0;
                            $elapsedMs = intval((microtime(true) - $inicioEnvio) * 1000);

                            if ($lineaNodes->length > 0) {
                                foreach ($lineaNodes as $node) {
                                    // Extraer NumSerieFactura (primero intentamos las formas namespaced y luego sin namespace)
                                    $numSerieNode = $xpath->query('.//tik:IDFactura/tik:NumSerieFactura', $node);
                                    if ($numSerieNode->length === 0) {
                                        $numSerieNode = $xpath->query('.//tik:NumSerieFactura', $node);
                                    }
                                    if ($numSerieNode->length === 0) {
                                        $numSerieNode = $xpath->query('.//IDFactura/NumSerieFactura', $node);
                                    }
                                    if ($numSerieNode->length === 0) {
                                        $numSerieNode = $xpath->query('.//NumSerieFactura', $node);
                                    }

                                    $numSerie = $numSerieNode->length ? trim($numSerieNode->item(0)->textContent) : '';

                                    if ($numSerie === '') {
                                        // Si no hay referencia de serie, intentamos extraer IDEmisor + Fecha para localizar si es necesario (opcional)
                                        // En este ejemplo saltamos si no encontramos NumSerie.
                                        continue;
                                    }

                                    // buscar factura en el grupo por numSerieFactura (normalizando)
                                    $facturaMatch = $agrupables->first(function ($f) use ($numSerie) {
                                        return strtoupper(trim((string)$f->numSerieFactura)) === strtoupper(trim($numSerie));
                                    });

                                    if (! $facturaMatch) {
                                        // no encontrada: saltar (puede ser que la factura no exista en BD o haya otra normalización)
                                        continue;
                                    }

                                    // Extraer EstadoRegistro y DescripcionErrorRegistro (varias rutas posibles)
                                    $estadoNode = $xpath->query('.//tikR:EstadoRegistro | .//EstadoRegistro', $node);
                                    $estadoRegistro = $estadoNode->length ? trim($estadoNode->item(0)->textContent) : '';

                                    $descripcionNode = $xpath->query('.//tikR:DescripcionErrorRegistro | .//DescripcionErrorRegistro', $node);
                                    $descripcionError = $descripcionNode->length ? trim($descripcionNode->item(0)->textContent) : '';

                                    // Comprueba RegistroDuplicado -> EstadoRegistroDuplicado (si existe)
                                    $registroDuplicadoNode = $xpath->query('.//tik:RegistroDuplicado/tik:EstadoRegistroDuplicado | .//RegistroDuplicado/EstadoRegistroDuplicado | .//EstadoRegistroDuplicado', $node);
                                    $estadoRegistroDuplicado = $registroDuplicadoNode->length ? trim($registroDuplicadoNode->item(0)->textContent) : '';
                                    $aceptadoConErrores = ($estadoRegistroDuplicado === 'AceptadoConErrores');

                                    // Mapear a estados de la BD como lo haces en el flujo individual
                                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores) {
                                        $facturaMatch->estado_proceso = 0;
                                        $facturaMatch->estado_registro = 1;
                                        $facturaMatch->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                                            ? 'Aceptada con errores: ' . ($descripcionError ?: $estadoRegistroDuplicado)
                                            : null;
                                        $facturaMatch->save();
                                        $acceptedCount++;
                                    } elseif ($estadoRegistro === 'Incorrecto') {
                                        $facturaMatch->estado_proceso = 0;
                                        $facturaMatch->estado_registro = 2;
                                        $facturaMatch->error = 'Rechazada: ' . $descripcionError;
                                        $facturaMatch->save();
                                    } else {
                                        // Estado no reconocido: guardamos para inspección
                                        $facturaMatch->estado_proceso = 0;
                                        $facturaMatch->estado_registro = 2;
                                        $facturaMatch->error = "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - Descripcion=$descripcionError - XML bruto: " . trim($dom->saveXML($node));
                                        $facturaMatch->save();
                                    }
                                } // foreach lineas

                                // contabilizar tiempos y facturas aceptadas
                                if ($acceptedCount > 0) {
                                    $totalFacturas += $acceptedCount;
                                    $totalTiempo += $elapsedMs;
                                }
                            } else {
                                // No se encontraron RespuestaLinea: marcar todas las facturas del grupo con error
                                foreach ($agrupables as $f) {
                                    $f->estado_proceso = 1;
                                    $f->estado_registro = 0;
                                    $f->error = 'No se encontraron RespuestaLinea en la respuesta agrupada.';
                                    $f->save();
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Error al enviar/procesar agrupado: marcar todas las facturas del grupo como error
                        foreach ($agrupables as $f) {
                            $f->estado_proceso = 1;
                            $f->estado_registro = 0;
                            $f->error = 'Error en envío/procesado agrupado: ' . $e->getMessage();
                            $f->save();
                        }
                        continue;
                    }
                } // foreach porEmisor
            }

            if ($individuales->isNotEmpty()) {
                foreach ($individuales as $facturaInd) {
                    try {
                        $xmlUnico = $agrupadorXmlService->buildGroupedXml(collect([$facturaInd]));

                        $ejercicio = $facturaInd->ejercicio;
                        $mes = date('m');

                        $carpetaBase = storage_path('facturas');
                        $carpetaTipo = $carpetaBase . '/facturasNoVerifactu';
                        $carpetaCif  = $carpetaTipo . '/' . $cifEmisor;
                        $carpetaEjercicio = $carpetaCif . '/' . $ejercicio;
                        $carpetaMes = $carpetaEjercicio . '/' . $mes;

                        foreach ([$carpetaBase, $carpetaTipo, $carpetaCif, $carpetaEjercicio, $carpetaMes] as $dir) {
                            if (!file_exists($dir)) {
                                @mkdir($dir, 0755, true);
                            }
                        }

                        $nombreArchivo = 'EJERCICIO-' . $facturaInd->ejercicio
                            . '_MES-' . date('m')
                            . '_SERIE-' . $facturaInd->serie
                            . '_NUM-' . $facturaInd->numFactura
                            . '.xml';

                        $rutaUnico = $carpetaMes . '/' . $nombreArchivo;
                        file_put_contents($rutaUnico, $xmlUnico);

                        $facturaInd->estado_proceso = 0;
                        $facturaInd->estado_registro = 3;
                        $facturaInd->error = null;
                        $facturaInd->save();
                    } catch (\Throwable $e) {
                        $facturaInd->estado_proceso = 1;
                        $facturaInd->estado_registro = 0;
                        $facturaInd->error = 'Error creando XML no Verifactu: ' . $e->getMessage();
                        $facturaInd->save();
                        continue;
                    }
                }
            }
        } // foreach porEmisor
        // Guardar logs
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
                'message' => "Facturas procesadas: $totalFacturas",
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Token incorrecto"
            ]);
        }
    }

    public function verifactuPruebaAntiguo(Request $request)
    {
        //Añadimos un token a la url para la petición get
        $token = $request->query('token');

        //Llamamos al servicio del certificado digital para luego enviarselo a la api
        $verifactuService = new ClientesSOAPVerifactu();

        //Guardamos el total de facturas que se han guardado
        $totalFacturas = 0;
        $totalTiempo = 0;

        // Instancias necesarias (usamos tu generator y el servicio agrupador que creamos)
        $xmlGenerator = new \App\Services\FacturaXmlGenerator();
        $agrupadorXmlService = new \App\Services\AgrupadorFacturasXmlService($xmlGenerator);

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
                $currentId = $factura->id;

                //Filtramos los datos de la factura anterior, para luego pornerlos en los campos de la huella actual y poder calcular la huella
                //Si es la primera factura de la serie, se ponen los mismos datos que la misma

                $numFacturaAnterior = $numero - 1;

                // Buscamos la primera factura anterior con misma serie pero con el número de factura anterior
                $facturaAnterior = Facturas::where('idEmisorFactura', $cifEmisor)
                    ->where('id', '<', $currentId)
                    ->orderBy('id', 'desc')
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


                // Generar y guardar XML(Storage/facturas/...)
                $xml = (new FacturaXmlGenerator())->generateXml($factura);
                $carpetaOrigen = storage_path('facturas');
                $ruta = $carpetaOrigen . '/' . $factura->nombreEmisor . '_' . $factura->serie . '_' . $factura->numFactura . '-' . $factura->ejercicio . '.xml';
                file_put_contents($ruta, $xml);

                // Enviar factura
                //Paso por parámetros el cif de la factura para actualizar la ruta de almacenamiento de certificado
                $verifactuService->actualizarRutas($factura->idEmisorFactura);
                $respuestaXml = $verifactuService->enviarFactura($xml);

                //Comprobamos si el xml tiene algún error interno en el cuerpo y lo convertimos en un objeto
                libxml_use_internal_errors(true);
                $respuestaXmlObj = simplexml_load_string($respuestaXml);

                //Si está mal el xml, quitamos todos los espacios innecesarios del cuerpo del xml
                if ($respuestaXmlObj !== false) {
                    $namespaces = $respuestaXmlObj->getNamespaces(true);
                    $body = $respuestaXmlObj->children($namespaces['env'])->Body ?? null;

                    $faultString = '';
                    if ($body) {
                        $faultNodes = $body->children($namespaces['env'])->Fault ?? null;
                        if ($faultNodes) {
                            foreach ($faultNodes->children() as $child) {
                                if ($child->getName() === 'faultstring') {
                                    $faultString = (string) $child;
                                    break;
                                }
                            }
                        }
                    }

                    //Almacenamos las posibles respuestas de la AEAT
                    $estadoRegistro = '';
                    $descripcionError = '';
                    $aceptadoConErrores = false;

                    //Buscamos en las etiquetas de la respuesta de la AEAT, que siempre empiezan por 'tikR'
                    //Almacenamos el estado del registro: correcta, incorrecta o aceptada con errores.
                    //Almacenamos también la descripción del error
                    //Y también almacenamos si es duplicado
                    if (isset($namespaces['tikR'])) {
                        $respuestaSII = $body?->children($namespaces['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                        $respuestaLinea = $respuestaSII?->children($namespaces['tikR'])->RespuestaLinea ?? null;

                        $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])->EstadoRegistro ?? '';
                        $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])->DescripcionErrorRegistro ?? '';

                        $registroDuplicado = $respuestaLinea?->children($namespaces['tikR'])->RegistroDuplicado ?? null;
                        $estadoRegistroDuplicado = (string) $registroDuplicado?->children($namespaces['tik'])->EstadoRegistroDuplicado ?? '';
                        if ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                            $aceptadoConErrores = true;
                        }
                    }

                    //Comprobamos si es correcto, aceptado con errores o incorrecto. Entonces cambiamos los campos el estado_proceso y el estado_registro
                    //Una vez comprobado se guarda la factura
                    if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 1;
                        $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                            ? 'Aceptada con errores: ' . $descripcionError
                            : null;
                    } elseif ($estadoRegistro === 'Incorrecto') {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = 'Rechazada: ' . $descripcionError;
                    } else {

                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = $faultString
                            ? "Respuesta no reconocida: $faultString"
                            : "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - aceptadoConErrores=$aceptadoConErrores - XML bruto: $respuestaXml";
                    }
                } else {
                    $factura->estado_proceso = 1;
                    $factura->estado_registro = 0;
                    $factura->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                }

                $factura->save();

                //Tiempo de proceso
                //Aquí calculamos el tiempo que se ha tardado la factura en milisegundos en generar todos los procesos anteriores y sumarlos entre todas las facturas para saber la media

                if ($factura->estado_registro === 1) {
                    $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                    $totalFacturas++;
                    $totalTiempo += $tiempoMs;
                }
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

    public function verifactuLock(Request $request)
    {
        $token = $request->query('token');

        if ($token !== 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'success' => false,
                'message' => 'Token incorrecto'
            ]);
        }

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $verifactuService = new ClientesSOAPVerifactu();
        $xmlGenerator = new \App\Services\FacturaXmlGenerator();
        $agrupadorXmlService = new \App\Services\AgrupadorFacturasXmlService($xmlGenerator);

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturas = Facturas::where('estado_proceso', 1)
            ->where('estado_registro', 0)
            ->orderBy('idEmisorFactura')
            ->orderBy('id')
            ->get();

        if ($facturas->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay facturas bloqueadas para procesar'
            ]);
        }

        $porEmisor = $facturas->groupBy('idEmisorFactura');

        foreach ($porEmisor as $cifEmisor => $grupo) {

            // Separación del modo verifactu y no-verifactu
            $verifactu = $grupo->filter(fn($f) => intval($f->modo_verifactu) === 1)->values();
            $noVerifactu = $grupo->filter(fn($f) => intval($f->modo_verifactu) === 0)->values();

            // ===== EL MODO NO VERIFACTU NO SE ENVÍA =====
            foreach ($noVerifactu as $factura) {
                try {
                    $xml = $agrupadorXmlService->buildGroupedXml(collect([$factura]));

                    $ejercicio = $factura->ejercicio;
                    $mes = date('m');

                    $base = storage_path('facturas/facturasNoVerifactu');
                    $ruta = "$base/$cifEmisor/$ejercicio/$mes";

                    if (!file_exists($ruta)) {
                        mkdir($ruta, 0755, true);
                    }

                    $nombreArchivo = 'EJERCICIO-' . $ejercicio
                        . '_MES-' . $mes
                        . '_SERIE-' . $factura->serie
                        . '_NUM-' . $factura->numFactura
                        . '.xml';

                    file_put_contents("$ruta/$nombreArchivo", $xml);

                    $factura->estado_proceso = 0;
                    $factura->estado_registro = 3;
                    $factura->error = null;
                    $factura->save();
                } catch (\Throwable $e) {
                    $factura->estado_proceso = 1;
                    $factura->estado_registro = 0;
                    $factura->error = 'Error NoVerifactu lock: ' . $e->getMessage();
                    $factura->save();
                }
            }

            // Reintento del envío de Verifactu

            if ($verifactu->isNotEmpty()) {
                $inicio = microtime(true);

                try {
                    $xmlAgrupado = $agrupadorXmlService->buildGroupedXml($verifactu);

                    $first = $verifactu->first();
                    $ejercicio = $first->ejercicio;
                    $mes = date('m');

                    $base = storage_path('facturas/facturasVerifactu');
                    $ruta = "$base/$cifEmisor/$ejercicio/$mes";

                    if (!file_exists($ruta)) {
                        mkdir($ruta, 0755, true);
                    }

                    $nombreArchivo = 'VERIFACTU_AGRUPADO'
                        . '_CIF-' . $cifEmisor
                        . '_EJERCICIO-' . $ejercicio
                        . '_MES-' . $mes
                        . '_TOTAL-' . $verifactu->count()
                        . '_FACTURAS.xml';

                    file_put_contents("$ruta/$nombreArchivo", $xmlAgrupado);

                    $verifactuService->actualizarRutas($cifEmisor);
                    $respuestaXml = $verifactuService->enviarFactura($xmlAgrupado);
                    libxml_use_internal_errors(true);
                    $respuestaObj = simplexml_load_string($respuestaXml);

                    $estadoRegistro = '';
                    $descripcionError = '';

                    if ($respuestaObj !== false) {
                        $namespaces = $respuestaObj->getNamespaces(true);
                        $body = $respuestaObj->children($namespaces['env'])->Body ?? null;

                        if ($body && isset($namespaces['tikR'])) {
                            $respuestaSII = $body->children($namespaces['tikR'])
                                ->RespuestaRegFactuSistemaFacturacion ?? null;

                            $respuestaLinea = $respuestaSII?->children($namespaces['tikR'])
                                ->RespuestaLinea ?? null;

                            $estadoRegistro = (string) $respuestaLinea?->children($namespaces['tikR'])
                                ->EstadoRegistro ?? '';

                            $descripcionError = (string) $respuestaLinea?->children($namespaces['tikR'])
                                ->DescripcionErrorRegistro ?? '';
                        }
                    }

                    foreach ($verifactu as $factura) {
                        if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                            $factura->estado_proceso = 0;
                            $factura->estado_registro = 1;
                            $factura->error = $estadoRegistro === 'AceptadoConErrores'
                                ? 'Aceptada con errores: ' . $descripcionError
                                : null;
                        } else {
                            $factura->estado_proceso = 0;
                            $factura->estado_registro = 2;
                            $factura->error = $descripcionError
                                ?: 'Respuesta AEAT no reconocida';
                        }

                        $factura->save();
                    }

                    $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                    $totalFacturas += $verifactu->count();
                    $totalTiempo += $tiempoMs;
                } catch (\Throwable $e) {
                    foreach ($verifactu as $f) {
                        $f->estado_proceso = 1;
                        $f->estado_registro = 0;
                        $f->error = 'Error Verifactu lock: ' . $e->getMessage();
                        $f->save();
                    }
                }
            }
        }

        if ($totalFacturas > 0) {
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => intval($totalTiempo / $totalFacturas),
                'periodo' => now()->startOfMinute(),
                'tipo_factura' => 'bloqueadas',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Facturas reprocesadas: $totalFacturas"
        ]);
    }
}
