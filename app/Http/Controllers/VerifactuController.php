<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturas;
use App\Services\ClientesSOAPVerifactu;
use App\Services\FacturaXmlGenerator;
use App\Services\AgrupadorFacturasXmlService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VerifactuController extends Controller
{

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

        $verifactuService = new ClientesSOAPVerifactu();

        $totalFacturas = 0;
        $totalTiempo = 0;

        $facturasLock = Facturas::where('estado_proceso', 1)
            ->where('estado_registro', 0)->get();

        foreach ($facturasLock as $factura) {
            //Guardamos el tiempo que tarda una factura en generarse y mandarse a la API
            $inicio = microtime(true);

            try {
                //Almacenamos los datos del numero de serie, la fecha y el cif del emisor
                $numero = $factura->numFactura;
                $serie = $factura->serie;
                $fechaEjercicio = $factura->ejercicio;
                $cifEmisor = $factura->idEmisorFactura;

                //Almacenamos también la fechaHoraHusoGenRegistro para comprobar que no es posterior a la de la fecha actual
                $fechaGeneracion = $factura->fechaHoraHusoGenRegistro;

                //Aquí comprobamos si la fechaHoraHusoGenRegistro es posterior a la fecha actual
                if ($fechaGeneracion) {
                    $fechaGeneracionCarbon = Carbon::parse($fechaGeneracion);
                    $fechaActualServidor = Carbon::now();

                    if ($fechaGeneracionCarbon->gt($fechaActualServidor)) {
                        $factura->estado_proceso = 0;
                        $factura->estado_registro = 2;
                        $factura->error = "La fecha de la factura es posterior a la fecha actual";
                        $factura->save();
                        break;
                    }
                }

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
                    } else {
                        // No encontrada: deja vacíos o nulos
                        $factura->IDEmisorFacturaAnterior = '';
                        $factura->numSerieFacturaAnterior = '';
                        $factura->FechaExpedicionFacturaAnterior = '';
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

                // Enviar factura
                //Paso por parámetros el cif de la factura para actualizar la ruta de almacenamiento de certificado
                $verifactuService->actualizarRutas($factura->idEmisorFactura);
                $respuestaXml = $verifactuService->enviarFactura($xml);

                //Comprobamos si el xml tiene algún error interno en el cuerpo y lo convertimos as String
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
                $tiempoMs = intval((microtime(true) - $inicio) * 1000);
                $totalFacturas++;
                $totalTiempo += $tiempoMs;
            } catch (\Throwable $e) {
                // Error general
                //Si hay algún tipo de error en el servidor o interno, la factura se queda bloqueada y se guarda
                $factura->estado_proceso = 1;
                $factura->estado_registro = 0;
                $factura->error = $e->getMessage();
                $factura->save();
            }
        }

        if ($totalFacturas > 0) {
            $mediaTiempo = intval($totalTiempo / $totalFacturas);
            DB::table('facturas_logs')->insert([
                'cantidad_facturas' => $totalFacturas,
                'media_tiempo_ms' => $mediaTiempo,
                'periodo' => now()->startOfMinute(),
                'tipo_factura' => 'bloqueadas',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if ($token === 'sZQe4cxaEWeFBe3EPkeah0KqowVBLx') {
            return response()->json([
                'success' => true,
                'message' => "Facturas generadas $totalFacturas"
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Token incorrecto'
            ]);
        }
    }

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

            // Construir XML agrupado PARA ESTE EMISOR (incluye N sum:RegistroFactura)
            try {
                $xmlAgrupado = $agrupadorXmlService->buildGroupedXml($grupo);

                // Guardar UN SÓLO fichero en storage/facturas por emisor
                $carpetaOrigen = storage_path('facturas');
                if (!file_exists($carpetaOrigen)) {
                    @mkdir($carpetaOrigen, 0755, true);
                }
                $rutaAgrupado = $carpetaOrigen . '/agrupado_' . $cifEmisor . '_' . date('Ymd_His') . '.xml';
                file_put_contents($rutaAgrupado, $xmlAgrupado);
            } catch (\Throwable $e) {
                // Si falla la creación del XML agrupado para este emisor, marcamos todas sus facturas con error y seguimos con el siguiente emisor
                foreach ($grupo as $f) {
                    $f->estado_proceso = 1;
                    $f->estado_registro = 0;
                    $f->error = 'Error creando XML agrupado: ' . $e->getMessage();
                    $f->save();
                }
                continue;
            }

            // Enviar el XML agrupado UNA SOLA VEZ para este emisor y procesar la respuesta
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
                    foreach ($grupo as $f) {
                        $f->estado_proceso = 1;
                        $f->estado_registro = 0;
                        $f->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                        $f->save();
                    }
                    continue;
                }

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
                        $factura = $grupo->first(function ($f) use ($numSerie) {
                            return strtoupper(trim((string)$f->numSerieFactura)) === strtoupper(trim($numSerie));
                        });

                        if (! $factura) {
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
                            $factura->estado_proceso = 0;
                            $factura->estado_registro = 1;
                            $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                                ? 'Aceptada con errores: ' . ($descripcionError ?: $estadoRegistroDuplicado)
                                : null;
                            $factura->save();
                            $acceptedCount++;
                        } elseif ($estadoRegistro === 'Incorrecto') {
                            $factura->estado_proceso = 0;
                            $factura->estado_registro = 2;
                            $factura->error = 'Rechazada: ' . $descripcionError;
                            $factura->save();
                        } else {
                            // Estado no reconocido: guardamos para inspección
                            $factura->estado_proceso = 0;
                            $factura->estado_registro = 2;
                            $factura->error = "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - Descripcion=$descripcionError - XML bruto: " . trim($dom->saveXML($node));
                            $factura->save();
                        }
                    } // foreach lineas

                    // contabilizar tiempos y facturas aceptadas
                    if ($acceptedCount > 0) {
                        $totalFacturas += $acceptedCount;
                        $totalTiempo += $elapsedMs;
                    }
                } else {
                    // No se encontraron RespuestaLinea: marcar todas las facturas del grupo con error
                    foreach ($grupo as $f) {
                        $f->estado_proceso = 1;
                        $f->estado_registro = 0;
                        $f->error = 'No se encontraron RespuestaLinea en la respuesta agrupada.';
                        $f->save();
                    }
                }
            } catch (\Throwable $e) {
                // Error al enviar/procesar agrupado: marcar todas las facturas del grupo como error
                foreach ($grupo as $f) {
                    $f->estado_proceso = 1;
                    $f->estado_registro = 0;
                    $f->error = 'Error en envío/procesado agrupado: ' . $e->getMessage();
                    $f->save();
                }
                continue;
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
}
