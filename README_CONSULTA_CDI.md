# ConsultaCDIController – Validación de NIF frente a AEAT y VIES

Este documento describe, a nivel funcional, el comportamiento del `ConsultaCDIController`, que centraliza la validación de NIF/NIE/CIF tanto a nivel nacional (AEAT) como intracomunitario (VIES).

## Responsabilidad general

El controlador `ConsultaCDIController` se encarga de:

- Exponer un único punto de entrada para validar un NIF o CIF proporcionado por un cliente.
- Verificar un token de seguridad que llega en el cuerpo de la petición.
- Redirigir la validación al servicio correspondiente según el tipo de documento:
  - Validación nacional a través de la AEAT.
  - Validación intracomunitaria a través de VIES.
  - Tratamiento específico para NIF extranjeros.
- Interpretar la respuesta de cada servicio y devolver un resultado normalizado.

## Entrada esperada

El método principal recibe, en el cuerpo de la petición, al menos:

- `nif`: documento de identificación (NIF/CIF/VAT) a validar. 
- `nombre`: nombre o razón social asociados al documento.
- `token`: cadena obligatoria para autorizar la petición.
- `idTypeNum` (opcional): indica el tipo de documento:
  - `01`: NIF nacional (validación ante AEAT).
  - `02`: NIF intracomunitario (consulta VIES).
  - `03`: NIF extranjero (tratamiento propio).

La primera validación del controlador consiste en:

- Asegurar que todos los campos obligatorios están presentes.
- Verificar que el `token` coincide con el valor esperado.
- Comprobar que `idTypeNum`, si viene informado, está entre los valores permitidos.
- Usar `01` por defecto cuando no se informe ningún tipo.

## Flujo para NIF intracomunitario (idTypeNum = 02)

Cuando el tipo de documento es intracomunitario:

- Se aplica una pequeña protección anti-abuso por IP:
  - El controlador verifica el tiempo transcurrido desde la última petición para ese cliente.
  - Si el intervalo es menor a un umbral definido (por ejemplo, 5 segundos), se devuelve un error de “demasiadas peticiones”.
- Se normaliza el NIF/VAT:
  - Se convierte a mayúsculas.
  - Se eliminan espacios y guiones.
  - Se separa el código de país (primeros 2 caracteres) del número de VAT.
- Se comprueba que el código de país son letras y que hay un número de VAT no vacío.
- Se llama al servicio `ClientesSOAPConsultaVIES` con el VAT normalizado.
- El servicio VIES devuelve un XML (o un error) que el controlador interpreta:
  - Si hay un fallo de concurrencia (código específico `MS_MAX_CONCURRENT_REQ`), se considera como validación aceptada.
  - En respuestas normales, se lee el nodo `valid` para saber si el VAT es válido o no.
- La respuesta del endpoint:
  - Si el VAT es válido, devuelve `success = true` y el nombre en mayúsculas.
  - Si no es válido, devuelve `success = false` y un mensaje indicando que el NIF intracomunitario no es válido.

## Flujo para NIF extranjero (idTypeNum = 03)

Cuando el tipo de documento corresponde a un NIF extranjero:

- El controlador no intenta registrarlo ni validarlo ante la AEAT o VIES.
- Devuelve de forma directa una respuesta indicando que no se pueden registrar NIF extranjeros.

## Flujo para NIF nacional (idTypeNum = 01)

Para la validación estándar frente a la AEAT:

- Se toma el NIF y el nombre enviados por el cliente y se convierten a mayúsculas.
- Se llama al servicio `ClientesSOAPConsultaCDI`, pasando el NIF y el nombre normalizados.
- El servicio devuelve una respuesta en XML, que el controlador:
  - Carga como XML.
  - Enlaza con los namespaces adecuados para localizar los datos relevantes.
  - Busca el nodo `Resultado` para saber si está “IDENTIFICADO” o no.
  - Busca el nodo `Nombre` para conocer el nombre real asociado en la AEAT.
- A partir de esa información:
  - Si el resultado es “IDENTIFICADO”, el controlador marca `success = true` y devuelve en `message` el nombre real asociado en la respuesta de la AEAT.
  - En cualquier otro caso, `success` se mantiene en `false` y `message` refleja el texto del resultado devuelto por la AEAT.

## Respuesta del endpoint

Independientemente del flujo elegido, la respuesta final del controlador siempre sigue la misma estructura básica:

- `success`: indica si la validación ha sido satisfactoria según el criterio del servicio consultado.
- `message`: mensaje con el resultado de la validación:
  - Puede contener el nombre validado (cuando está identificado).
  - Puede mostrar el tipo de error o el motivo por el que no se ha podido validar.

De esta forma, `ConsultaCDIController` encapsula la lógica de validación de NIFs en un único endpoint, gestionando el token, la selección del servicio adecuado y la interpretación de las respuestas externas.

