# VerifactuController – Envío de facturas a Verifactu/AEAT

Este documento describe, a nivel funcional, el comportamiento del `VerifactuController` y el flujo que sigue para seleccionar, preparar y enviar facturas al servicio Verifactu/AEAT, así como para procesar las respuestas.

## Responsabilidad general

El controlador `VerifactuController` se encarga de:

- Localizar en la base de datos las facturas pendientes de envío a Verifactu/AEAT.
- Agrupar facturas por emisor para poder generar XML agrupados (un fichero por emisor).
- Generar los XML de facturas individuales o agrupadas, según el modo configurado en cada factura.
- Enviar esos XML al servicio web de Verifactu mediante el cliente SOAP.
- Interpretar las respuestas de Verifactu y actualizar el estado de cada factura en la base de datos.
- Registrar, cuando procede, información de tiempos y número de facturas aceptadas para poder hacer métricas básicas de rendimiento.
- Proteger el endpoint mediante un token que se debe pasar en la URL.

## Selección y agrupación de facturas

Al invocar el método principal:

- Se recibe el `token` de seguridad como parámetro de la petición (query string).
- Se buscan en la tabla de facturas todas aquellas que:
  - Están “desbloqueadas” (`estado_proceso = 0`).
  - Todavía no se han presentado a la AEAT (`estado_registro = 0`).
- Si no hay facturas pendientes:
  - Si el token es correcto, se devuelve una respuesta indicando que no hay nada que procesar.
  - Si el token es incorrecto, se informa de token inválido.
- Las facturas encontradas se agrupan por emisor (`idEmisorFactura`) para construir un XML por cada emisor.
- Antes de generar los XML, se recalcula la información de encadenamiento (factura anterior del mismo emisor, huella anterior, etc.) para cada factura dentro de su grupo.

Cada factura, además, puede estar en diferentes “modos”:

- Modo agrupado: facturas que se pueden enviar en un mismo XML con varias entradas de `RegistroFactura`.
- Modo individual: facturas que se envían una a una en XML separados.
- Modo SII: facturas que deben seguir un flujo específico para SII (cuando esta opción está habilitada).

## Generación de XML y servicios utilizados

Para la construcción de los XML se utilizan servicios específicos de la capa de negocio:

- Un generador de XML de factura (`FacturaXmlGenerator`), responsable de transformar una factura de la base de datos en su representación XML compatible con Verifactu.
- Un servicio de agrupación (`AgrupadorFacturasXmlService`) que:
  - Recibe un conjunto de facturas agrupables de un mismo emisor.
  - Construye un único XML con tantas entradas de factura como facturas haya en el grupo.

El controlador:

- Crea una única instancia de estos servicios y la reutiliza durante todo el proceso.
- Genera los XML:
  - Agrupados, para las facturas marcadas como “agrupables”.
  - Individuales, para las facturas que requieren un tratamiento por separado.
- Decide la forma en la que se guardan los XML en el sistema de ficheros:
  - Carpeta raíz de facturas.
  - Subcarpeta para Verifactu.
  - Carpeta del CIF del emisor.
  - Subcarpetas por ejercicio y mes.
  - Nombre de fichero que identifica tipo de envío, emisor y fecha.

## Envío a Verifactu y tratamiento de respuestas

El envío a Verifactu se realiza a través del servicio SOAP `ClientesSOAPVerifactu`, que se encarga de:

- Firmar o preparar el mensaje según sea necesario.
- Ejecutar la llamada SOAP a la API de Verifactu/AEAT.
- Devolver al controlador el XML de respuesta.

Una vez recibida la respuesta, el controlador:

- Procesa el XML de respuesta mediante técnicas de parsing (DOM, XPath, etc.).
- Localiza los nodos que corresponden a cada factura enviada (por ejemplo, a través del número de serie de la factura).
- Extrae el resultado de cada registro (correcto, incorrecto, aceptado con errores, duplicado, etc.).
- Actualiza el estado de cada factura en la base de datos:
  - Marca como registrada en la AEAT si el resultado es correcto o aceptado con errores.
  - Marca como rechazada si el resultado es incorrecto.
  - Guarda, cuando existe, la descripción de error devuelta por Verifactu.
- En caso de errores globales de la llamada o de respuesta no reconocida:
  - Marca las facturas afectadas con un estado de error y conserva el XML de respuesta para diagnóstico.

## Estructura de carpetas y trazabilidad

El controlador también define cómo se almacenan los XML de envío y, en algunos casos, las respuestas:

- Los XML de solicitud se guardan en rutas organizadas por:
  - Tipo de operación (Verifactu).
  - CIF del emisor.
  - Ejercicio y mes.
- El almacenamiento y nombrado de ficheros facilita:
  - Localizar el XML original de un envío concreto.
  - Relacionar fácilmente las respuestas con sus peticiones.

## Respuestas del endpoint

Dependiendo de la situación, el controlador puede devolver:

- Un mensaje indicando que no hay facturas pendientes (si el token es válido).
- Un error de token incorrecto si no coincide con el esperado.
- Un resumen del procesamiento cuando hay envíos:
  - Número de facturas aceptadas.
  - Estado global de la operación.
  - Posible información adicional sobre tiempos de procesamiento.

De esta manera, `VerifactuController` actúa como la pieza central que coordina la selección de facturas, su transformación en XML, la comunicación con Verifactu y la actualización de estados en la base de datos.

