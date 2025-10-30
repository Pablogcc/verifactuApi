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

    public function verifactuAgrupados(Request $request)
{
    //Añadimos un token a la url para la petición get
    $token = $request->query('token');

    //Llamamos al servicio del certificado digital para luego enviarselo a la api
    $verifactuService = new ClientesSOAPVerifactu();

    //Guardamos el total de facturas que se han guardado
    $totalFacturas = 0;
    $totalTiempo = 0;

    // Instancias necesarias
    $xmlGenerator = new \App\Services\FacturaXmlGenerator();
    $agrupadorXmlService = new \App\Services\AgrupadorFacturasXmlService($xmlGenerator);

    //Almacenamos las facturas que su estado_proceso esté desbloqueada y su estado_registro esté sin presentar a la AEAT
    $facturas = Facturas::where('estado_proceso', 0)
        ->where('estado_registro', 0)
        ->orderBy('idEmisorFactura')
        ->orderBy('fechaExpedicionFactura')
        ->get();

    //Agrupamos por emisor y luego subdividimos por fechaExpedicionFactura|tipoImpositivo (normalizando fecha)
    $porEmisor = $facturas->groupBy('idEmisorFactura');

    foreach ($porEmisor as $grupo) {
        $subgrupos = $grupo->groupBy(function ($item) {
            $tipo = $item->tipoImpositivo ?? ($item->tipoImpositivo1 ?? '');
            $fecha = '';
            try {
                $fecha = $item->fechaExpedicionFactura ? \Carbon\Carbon::parse($item->fechaExpedicionFactura)->format('Y-m-d') : '';
            } catch (\Throwable $e) {
                $fecha = trim((string)($item->fechaExpedicionFactura ?? ''));
            }
            return $fecha . '|' . trim((string)$tipo);
        });

        foreach ($subgrupos as $subgrupo) {
            // Si el subgrupo tiene más de 1 factura -> agrupado; si solo 1 -> uso EXACTO del flujo uno-a-uno
            if ($subgrupo->count() > 1) {
                // --- RUTA AGRUPADA (UN ÚNICO XML por subgrupo) ---
                $inicio = microtime(true);

                try {
                    // Rellenar encadenamiento/huellas para cada factura (mismo comportamiento que tu verifactuPrueba)
                    foreach ($subgrupo as $factura) {
                        $numero = $factura->numFactura;
                        $serie = $factura->serie;
                        $fechaEjercicio = $factura->ejercicio;
                        $cifEmisor = $factura->idEmisorFactura;

                        if ($numero > 1) {
                            $numFacturaAnterior = $numero - 1;

                            // Usamos la misma búsqueda que tenías en el uno-a-uno para mantener exactamente la lógica
                            $facturaAnterior = Facturas::where('serie', $serie)
                                ->where('numFactura', $numFacturaAnterior)
                                ->where('ejercicio', $fechaEjercicio)
                                ->where('idEmisorFactura', $cifEmisor)
                                ->first();

                            if ($facturaAnterior) {
                                $factura->IDEmisorFacturaAnterior = $facturaAnterior->idEmisorFactura;
                                $factura->numSerieFacturaAnterior = $facturaAnterior->numSerieFactura;
                                $factura->FechaExpedicionFacturaAnterior = $facturaAnterior->fechaExpedicionFactura;
                                $factura->huellaAnterior = $facturaAnterior->huella;
                            } else {
                                $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                                $factura->numSerieFacturaAnterior = $factura->numSerieFactura;
                                $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                                $factura->huellaAnterior = $factura->huella;
                            }
                        } else {
                            $factura->IDEmisorFacturaAnterior = $factura->idEmisorFactura;
                            $factura->numSerieFacturaAnterior = $factura->serie . '/0000000';
                            $factura->FechaExpedicionFacturaAnterior = $factura->fechaExpedicionFactura;
                            $factura->huellaAnterior = $factura->huella;
                        }
                    }

                    // Construir XML agrupado (servicio SOLO construye la estructura con N sum:RegistroFactura)
                    $xmlAgrupado = $agrupadorXmlService->buildGroupedXml($subgrupo);

                    // (Opcional) Guardar UN SOLO XML por subgrupo — si no quieres guardarlo, comenta la siguiente línea
                    $carpetaOrigen = storage_path('facturas');
                    if (!file_exists($carpetaOrigen)) {
                        @mkdir($carpetaOrigen, 0755, true);
                    }
                    $rutaAgrupado = $carpetaOrigen . '/agrupado_' . $subgrupo->first()->idEmisorFactura . '_' . time() . '.xml';
                    file_put_contents($rutaAgrupado, $xmlAgrupado);

                    // Enviar agrupado (tu servicio ya maneja la firma según rutas)
                    $verifactuService->actualizarRutas($subgrupo->first()->idEmisorFactura);
                    $respuestaXml = $verifactuService->enviarFactura($xmlAgrupado);

                    // Procesar la respuesta agrupada: buscamos todas las RespuestaLinea y las mapeamos por NumSerieFactura
                    libxml_use_internal_errors(true);
                    $respuestaXmlObj = @simplexml_load_string($respuestaXml);

                    if ($respuestaXmlObj === false) {
                        // marcar todas las facturas del subgrupo como error de respuesta no XML
                        foreach ($subgrupo as $f) {
                            $f->estado_proceso = 1;
                            $f->estado_registro = 0;
                            $f->error = 'Respuesta no es XML válido: ' . $respuestaXml;
                            $f->save();
                        }
                        continue;
                    }

                    $namespaces = $respuestaXmlObj->getNamespaces(true);
                    $body = $respuestaXmlObj->children($namespaces['env'] ?? $namespaces['soapenv'] ?? null)->Body ?? null;

                    $lineas = [];
                    if ($body && isset($namespaces['tikR'])) {
                        $respuestaSII = $body->children($namespaces['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                        if ($respuestaSII) {
                            foreach ($respuestaSII->children($namespaces['tikR']) as $child) {
                                if ($child->getName() === 'RespuestaLinea') {
                                    $lineas[] = $child;
                                }
                            }
                        }
                    }

                    $acceptedCount = 0;
                    $elapsedMs = intval((microtime(true) - $inicio) * 1000);

                    if (count($lineas) > 0) {
                        foreach ($lineas as $rl) {
                            // extraer NumSerieFactura (varias alternativas)
                            $numSerie = (string) ($rl->NumSerieFactura ?? '');
                            if ($numSerie === '') {
                                $numSerie = (string) ($rl->IDFactura?->NumSerieFactura ?? '');
                            }

                            if ($numSerie === '') {
                                continue;
                            }

                            // buscar factura en el subgrupo (normalizamos trim/upper)
                            $factura = $subgrupo->first(function ($f) use ($numSerie) {
                                return strtoupper(trim((string)$f->numSerieFactura)) === strtoupper(trim($numSerie));
                            });

                            if (! $factura) {
                                // no encontrada: saltar
                                continue;
                            }

                            // Reutilizamos el parseo individual que ya tienes creando un sobre con la RespuestaLinea
                            $xmlLinea = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                            $xmlLinea .= "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tikR=\"https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd\">\n";
                            $xmlLinea .= "  <soapenv:Header/>\n";
                            $xmlLinea .= "  <soapenv:Body>\n";
                            $xmlLinea .= "    <tikR:RespuestaRegFactuSistemaFacturacion>\n";
                            $xmlLinea .= $rl->asXML();
                            $xmlLinea .= "    </tikR:RespuestaRegFactuSistemaFacturacion>\n";
                            $xmlLinea .= "  </soapenv:Body>\n";
                            $xmlLinea .= "</soapenv:Envelope>\n";

                            // Parseo de la respuesta de la línea (mismo comportamiento que tu verifactuPrueba)
                            libxml_use_internal_errors(true);
                            $rObj = @simplexml_load_string($xmlLinea);
                            if ($rObj === false) {
                                $factura->estado_proceso = 1;
                                $factura->estado_registro = 0;
                                $factura->error = 'RespuestaLinea no es XML válido';
                                $factura->save();
                                continue;
                            }
                            libxml_clear_errors();

                            $rNames = $rObj->getNamespaces(true);
                            $rBody = $rObj->children($rNames['env'] ?? $rNames['soapenv'] ?? null)->Body ?? null;

                            $estadoRegistro = '';
                            $descripcionError = '';
                            $aceptadoConErrores = false;

                            if (isset($rNames['tikR'])) {
                                $respuestaS = $rBody?->children($rNames['tikR'])->RespuestaRegFactuSistemaFacturacion ?? null;
                                $respuestaL = $respuestaS?->children($rNames['tikR'])->RespuestaLinea ?? null;

                                $estadoRegistro = (string) $respuestaL?->children($rNames['tikR'])->EstadoRegistro ?? '';
                                $descripcionError = (string) $respuestaL?->children($rNames['tikR'])->DescripcionErrorRegistro ?? '';

                                $registroDuplicado = $respuestaL?->children($rNames['tikR'])->RegistroDuplicado ?? null;
                                $estadoRegistroDuplicado = (string) ($registroDuplicado?->children($rNames['tik'])->EstadoRegistroDuplicado ?? '');
                                if ($estadoRegistroDuplicado === 'AceptadoConErrores') {
                                    $aceptadoConErrores = true;
                                }
                            }

                            if ($estadoRegistro === 'Correcto' || $estadoRegistro === 'AceptadoConErrores') {
                                $factura->estado_proceso = 0;
                                $factura->estado_registro = 1;
                                $factura->error = ($estadoRegistro === 'AceptadoConErrores' || $aceptadoConErrores)
                                    ? 'Aceptada con errores: ' . $descripcionError
                                    : null;
                                $factura->save();
                                $acceptedCount++;
                            } elseif ($estadoRegistro === 'Incorrecto') {
                                $factura->estado_proceso = 0;
                                $factura->estado_registro = 2;
                                $factura->error = 'Rechazada: ' . $descripcionError;
                                $factura->save();
                            } else {
                                $factura->estado_proceso = 0;
                                $factura->estado_registro = 2;
                                $factura->error = "Respuesta no reconocida: EstadoRegistro=$estadoRegistro - XML bruto: " . (string)$rl->asXML();
                                $factura->save();
                            }
                        } // foreach lineas

                        // contabilizar tiempos y facturas aceptadas
                        if ($acceptedCount > 0) {
                            $totalFacturas += $acceptedCount;
                            $totalTiempo += $elapsedMs; // sumar el tiempo total del envío agrupado (aprox.)
                        }
                    } else {
                        // No se encontraron RespuestaLinea: marcar todas las facturas del subgrupo con error
                        foreach ($subgrupo as $f) {
                            $f->estado_proceso = 1;
                            $f->estado_registro = 0;
                            $f->error = 'No se encontraron RespuestaLinea en la respuesta agrupada.';
                            $f->save();
                        }
                    }
                } catch (\Throwable $e) {
                    // Error al construir/enviar/procesar el agrupado: marcar todas
                    foreach ($subgrupo as $f) {
                        $f->estado_proceso = 1;
                        $f->estado_registro = 0;
                        $f->error = 'Error en envío/procesado agrupado: ' . $e->getMessage();
                        $f->save();
                    }
                    continue;
                }
            } else {
                // --- RUTA INDIVIDUAL (COMPORTAMIENTO EXACTO DE verifactuPrueba) ---
                foreach ($subgrupo as $factura) {
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
                } // foreach factura individual
            } // end if agrupado vs individual
        } // foreach subgrupos
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
