# CertificadosController – Gestión de certificados digitales de emisores

Este documento describe, a nivel funcional, el comportamiento del `CertificadosController`, que centraliza la validación, conversión y consulta de certificados digitales asociados a los emisores.

## Responsabilidad general

El controlador `CertificadosController` se encarga de:

- Recibir los certificados y datos de los emisores para validarlos y almacenarlos de forma segura.
- Convertir el material de certificado recibido (normalmente en Base64) en ficheros `cert.pem`, `key.pem` y `.pfx` utilizables por el resto de la aplicación.
- Extraer y verificar información del certificado (CIF del titular, fecha de validez).
- Guardar o actualizar la información del emisor en base de datos, cifrando los datos sensibles.
- Devolver información de estado sobre certificados existentes (fecha de caducidad, datos de contacto, etc.).
- Permitir generar un archivo `.pfx` a partir de ficheros `cert.pem` y `key.pem` ya almacenados.

## Conversión y validación de un certificado (`convertir`)

En la operación principal de alta/actualización de certificados:

- El endpoint recibe en el cuerpo de la petición:
  - `cif`: identificador fiscal del emisor.
  - `certificado`: contenido del certificado (normalmente en Base64).
  - `password`: contraseña asociada al certificado, encriptada.
  - `correoAdministrativo`: correo de contacto de la empresa.
  - `nombreEmpresa`: nombre de la empresa emisora.
  - `token`: cadena de seguridad para autorizar la operación.
- Se valida la estructura de la petición y el valor del token.
- Se utiliza el servicio `Encriptar` para:
  - Desencriptar la contraseña del certificado.
  - Desencriptar y generar los ficheros necesarios (`cert.pem`, `key.pem`) a partir del contenido recibido.
- Una vez obtenido el certificado en formato PEM:
  - Se lee el fichero `cert.pem` y se construye un objeto de certificado.
  - Se genera un fichero `.pfx` combinando el certificado y la clave privada, usando la contraseña desencriptada.
  - Se guarda el `.pfx` en la misma carpeta de certificados para ese CIF.
- A partir del certificado, se extrae:
  - La fecha de validez (fecha de caducidad) y se formatea para guardarla en base de datos.
  - El CIF que figura en el propio certificado, a partir de diferentes campos (CN, identificadores específicos u otros atributos).
- Se compara el CIF extraído del certificado con el CIF recibido en la petición:
  - Si no coinciden, se considera un error y se detiene el proceso.
- Finalmente, se actualizan o insertan los datos del emisor en la tabla correspondiente:
  - CIF.
  - Certificado y contraseña, normalmente almacenados cifrados.
  - Correo administrativo.
  - Nombre de la empresa.
  - Fecha de validez del certificado.
- La respuesta indica:
  - Si el certificado ha sido validado correctamente.
  - La fecha de validez detectada.
  - Posibles avisos si la caducidad está próxima.

En caso de cualquier error (formato inválido, fallo de lectura de ficheros, cif no coincidente, etc.), se devuelve una respuesta con `validado = false`, el posible `fechaValidez` (si se llegó a calcular) y el mensaje de error.

## Consulta del estado de un certificado (`comprobacionEstado`)

Cuando se quiere conocer el estado de un certificado ya almacenado:

- El endpoint recibe:
  - `cif`: CIF del emisor cuyo certificado se desea consultar.
  - `token`: valor de seguridad.
- El controlador:
  - Busca en la base de datos el emisor con ese CIF.
  - Si no existe, devuelve un error indicando que no hay certificado para ese CIF.
  - Si existe, usa el servicio `Encriptar` para desencriptar:
    - El correo administrativo.
    - El nombre de la empresa.
  - Construye una respuesta con:
    - `resultado = true`.
    - CIF del emisor.
    - Fecha de validez guardada.
    - Correo administrativo desencriptado.
    - Nombre de la empresa desencriptado.

Este método permite a otras partes del sistema comprobar rápidamente si un emisor tiene certificado válido y qué datos de contacto están asociados.

## Generación de un `.pfx` desde `cert.pem` y `key.pem` (`generarPfxDesdePem`)

En escenarios donde ya existen ficheros `cert.pem` y `key.pem` en disco y se necesita generar un `.pfx`:

- El endpoint recibe:
  - `cif`: CIF del emisor.
  - `token`: valor de seguridad.
- El controlador:
  - Localiza la carpeta `storage/certs/{cif}`.
  - Comprueba la existencia de `cert.pem` y `key.pem` en esa ruta.
  - Lee el contenido de ambos ficheros y construye:
    - Un objeto de certificado a partir de `cert.pem`.
    - Una clave privada a partir de `key.pem`.
  - Genera una contraseña aleatoria para el nuevo `.pfx`.
  - Ejecuta la exportación a formato `.pfx` usando certificado y clave privada.
  - Guarda el archivo `.pfx` en la misma carpeta y, además:
    - Devuelve el fichero `.pfx` codificado en Base64.
    - Informa de la ruta donde se ha guardado.
    - Devuelve la contraseña generada.

Si falta alguno de los ficheros necesarios o estos no son válidos, la respuesta indica claramente el motivo (fichero no encontrado, error de lectura, contenido inválido, etc.) y marca `resultado = false`.

## Notificaciones sobre caducidad de certificados (`notificacion`)

El controlador también incorpora un endpoint para revisar certificados próximos a caducar y lanzar avisos:

- Se valida un token de seguridad recibido en la query.
- Se calcula una fecha de aviso (por ejemplo, 30 días desde la fecha actual).
- Se localizan los emisores cuyo certificado caduca en o antes de esa fecha.
- Para cada emisor:
  - Se desencriptan correo y nombre de la empresa.
  - Se calcula cuántos días faltan para la caducidad o cuántos días han pasado desde que caducó.
  - Se determina el texto de estado (caducado, caduca en menos de X días, etc.).
  - Se compone un correo de aviso a partir de una plantilla y se envía usando un endpoint de correo externo.
- Al final, se devuelve un listado con:
  - CIF.
  - Nombre de la empresa.
  - Fecha de validez.
  - Días restantes o pasados.
  - Estado textual para cada certificado.

De esta forma, `CertificadosController` centraliza todo el ciclo de vida de los certificados digitales de los emisores: alta, validación, almacenamiento, consulta y avisos de caducidad.

