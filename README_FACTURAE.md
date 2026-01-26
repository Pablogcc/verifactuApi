# Facturación electrónica Facturae – Controlador y servicios principales

Este documento describe, a nivel funcional, qué hace el controlador de facturación electrónica basado en Facturae y cómo se apoyan en él los servicios de la capa `App\Services`. No incluye ejemplos de código, sólo el flujo y la lógica de negocio.

## Responsabilidad del controlador de Facturae

El controlador de facturación electrónica se encarga de:

- Exponer un endpoint que recibe una petición HTTP (normalmente en formato JSON) con:
  - Un `token` de seguridad.
  - Un indicador `firmada` que indica si el XML debe ir firmado o no.
  - Un bloque `factura` con los datos generales, direcciones, totales y líneas de detalle.
- Validar que la estructura del JSON cumpla todos los requisitos definidos:
  - Datos identificativos del emisor y del cliente.
  - Datos de fechas, serie, número y descripción de la operación.
  - Totales de la factura (base imponible, impuestos, importe total, pendiente de cobro, etc.).
  - Direcciones del emisor y del receptor, y, en su caso, de unidades administrativas (oficina contable, órgano gestor, unidad tramitadora).
  - Estructura y campos mínimos de cada línea de detalle (descripción, cantidad, precio, tipo de IVA, cuota, etc.).
- Verificar que el `token` recibido coincide con el token válido configurado en la aplicación; si no, la petición es rechazada.
- Localizar al emisor en la base de datos a partir del CIF incluido en los datos de la factura.
- Verificar que en el almacenamiento de certificados existe el archivo `.pfx` asociado a ese emisor.
- Coordinar la generación del XML Facturae y su firma (si se ha solicitado), apoyándose en los servicios de la capa de negocio.
- Definir la ruta de almacenamiento del XML, organizando las carpetas en función de:
  - Si la factura se guarda firmada o sin firmar.
  - El CIF del emisor.
  - El ejercicio (año) y el mes de la factura.
- Guardar el XML resultante en el sistema de ficheros, siguiendo la estructura anterior.
- Convertir el XML final en base64, encriptarlo y devolverlo en la respuesta JSON, junto con un indicador de éxito.

En resumen, el controlador actúa como orquestador del proceso completo: recibe datos, valida, busca al emisor y su certificado, genera el XML Facturae, lo firma opcionalmente, lo persiste en disco y devuelve el resultado de forma segura.

## Servicios implicados en el proceso

### Servicio de encriptación (`Encriptar`)

Este servicio gestiona el cifrado y descifrado de información sensible relacionada con la facturación electrónica, en particular:

- Desencripta la contraseña del certificado digital del emisor, que se almacena cifrada en la base de datos.
- Encripta el contenido del XML de la factura una vez convertido a texto en base64, antes de enviarlo al cliente.

De esta manera, tanto las credenciales del certificado como el fichero de factura generado se tratan de forma segura dentro de la aplicación.

### Servicio de generación de XML Facturae (`FacturaXmlElectronica`)

Este servicio construye el documento XML que representa la factura en el estándar Facturae. A partir de los datos de la petición:

- Forma la cabecera del fichero (versión de esquema, modalidad, tipo de emisor).
- Calcula y rellena los importes globales del lote (importe total de facturas, pendiente de cobro, ejecutable).
- Construye la información de partes (emisor y receptor), incluyendo datos fiscales y de localización.
- Genera la sección de facturas, donde se incluyen:
  - Datos generales de la factura (serie, número, fechas, descripción).
  - Totales de la factura, bases imponibles y desglose de impuestos.
  - Detalle de cada línea (concepto, cantidad, precios, impuestos, etc.).
- Adapta el espacio de nombres y la versión del esquema que se va a utilizar según se trate de un XML pensado para ser firmado o no.

El resultado de este servicio es un XML Facturae completo y coherente con los datos que el cliente ha enviado en la petición.

### Servicio de firma Facturae (`ChilkatLikeFacturaeSigner`)

Cuando la factura debe ir firmada, el controlador delega la firma del XML en este servicio. Su cometido principal es:

- Recibir el XML generado por el servicio de Facturae, junto con:
  - La ruta al certificado digital del emisor (archivo `.pfx`).
  - La contraseña real del certificado.
- Aplicar la firma digital sobre el XML siguiendo la política de firma esperada para documentos Facturae, incorporando:
  - Referencias al documento firmado.
  - Información del certificado utilizado.
  - Los valores criptográficos necesarios para la validación de la firma.
- Devolver un nuevo XML que contiene la firma embebida y totalmente preparada para su validación por parte de terceros.

Este servicio permite que el fichero generado cumpla los requisitos de firma exigidos para facturas electrónicas oficiales.

### Servicio de firma XML genérico (`FirmaXmlGeneratorElectronica`)

Además del servicio específico orientado a Facturae, existe un servicio de firma de XML más genérico, que:

- Trabaja directamente con certificados y claves almacenados en ficheros.
- Analiza el certificado y extrae la información necesaria para construir la estructura de firma.
- Ajusta los elementos de seguridad (digest, referencias, bloques de propiedades firmadas, etc.) para cumplir con los estándares de firma XML.

Este servicio representa una pieza reutilizable para otros escenarios de firma electrónica de documentos XML dentro del proyecto, aunque el controlador de facturación electrónica se apoye principalmente en el componente de firma específico de Facturae.

## Visión global del flujo de facturación electrónica

Desde el punto de vista funcional, el proceso completo que sigue la aplicación para generar una factura electrónica Facturae es:

1. El cliente envía una petición con el `token`, la indicación de si la factura debe ser firmada y todos los datos necesarios de la factura (cabecera, totales, direcciones y líneas).
2. El controlador valida que todos los campos requeridos existan y tengan el formato correcto.
3. Si el `token` no coincide con el configurado en la aplicación, se devuelve un error y el proceso termina.
4. Si el `token` es válido, se busca al emisor por su CIF y se verifica que dispone de un certificado cargado en el sistema.
5. Mediante el servicio de encriptación se obtiene la contraseña real del certificado para poder trabajar con él.
6. Se llama al servicio de generación de XML Facturae, que construye la representación XML de la factura con toda la información suministrada.
7. Si la factura debe ir firmada, el XML se pasa al servicio de firma, junto con el certificado del emisor, para producir un documento firmado electrónicamente.
8. A partir de la fecha de la factura se determinan el ejercicio y el mes, y con estos datos se prepara la estructura de carpetas para guardar el fichero en el almacenamiento.
9. Se genera un nombre de archivo que incorpora el ejercicio, el mes, la serie y el número de factura, y se guarda el XML (firmado o no) en la ruta correspondiente.
10. El XML resultante se codifica en base64, se encripta y se devuelve al cliente en la respuesta del endpoint, acompañado de un indicador de que el proceso ha finalizado correctamente.

Con estos pasos, la aplicación proporciona un flujo completo de emisión de facturas electrónicas en formato Facturae, integrando validación de datos, firma electrónica y almacenamiento organizado del documento generado.

