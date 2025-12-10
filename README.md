# Documentación Verifactu
---

## 1. Antes de empezar
Si te acabas de bajar el proyecto Laravel, lo primero es instalar dependencias:
```
composer install
```
Luego copia el `.env`:
```
cp .env.example .env
```
Y ya con eso lo tienes listo para configurarlo.

El controlador principal donde empieza la fiesta es:
```
app/Http/Controllers/VerifactuController
```

---

## 2. Cómo se procesan las facturas
Cada vez que el sistema se ejecuta (bien por API o por comando), hace esto:

### 2.1. Se buscan las facturas pendientes
Busca **todas las facturas**:
- con `estado_registro = 0` (sin presentar),
- `estado_proceso = 0` (no bloqueadas),
- y del emisor correspondiente.

Y las ordena por su ID para procesarlas en orden.

### 2.2. Se mira si hay una factura anterior
Para cada factura:
- Se busca la **anterior** según: número, serie, ejercicio y NIF del emisor.
- Si existe, se usan sus datos para rellenar los campos “anteriores”.
- Si **no existe**, se rellena con los datos de la propia factura (porque es la primera).

Esto se hace para calcular las huellas y enlazar unas facturas con otras.

---

## 3. Generación del XML — Servicio `FacturaXmlGenerator`
Aquí es donde se montan las etiquetas según el tipo de factura.

Este servicio:
1. Recibe la factura.
2. Monta el XML con todos los namespaces y estructura oficial de AEAT.
3. Lo guarda en `storage/facturas/` ordenado por minuto.

Y ya está. No tiene más misterio: solo genera XML tal y como AEAT quiere.

### Agrupación de facturas
Después, el servicio `AgrupadorFacturasXmlService` forma el XML final que se envía realmente a la AEAT.

---

## 4. Servicio `ClientesSOAPVerifactu`
Este servicio es el que **coge el XML y lo manda a la AEAT**.

Tiene dos tareas:

### 4.1. `actualizarRutas`
Busca en `storage/certs/` la carpeta que tiene el mismo NIF que el emisor y carga:
- cert.pem
- key.pem

Con esto ya sabe qué certificado usar.

### 4.2. `enviarFactura`
Aquí ya se envía la factura.

Se configura cURL con:
- URL del servicio AEAT
- XML
- Certificado y clave
- Validación del host y SSL

La AEAT responde con algo de esto:
- **Correcto** → Todo OK.
- **AceptadoConErrores** → Pasa, pero con advertencias.
- **Incorrecto** → Rechazado.

Luego el sistema:
- Limpia espacios raros.
- Extrae errores, si hay.
- Guarda el resultado.

### Cómo se guardan los estados
- **1** → Aceptado (sea limpio o con errores)
- **2** → Rechazado
- **0 + proceso=1** → Algo raro en la respuesta → se bloquea

---

## 5. Guardado en `facturas_logs`
Cada minuto el sistema lleva un pequeño registro:
- cuántas facturas se procesaron,
- cuánto tardó cada una,
- media total,
- y si eran facturas normales o si eran desbloqueadas.

Es básicamente un histórico para saber cómo rinde el sistema.

---

## 6. Endpoint para generar facturas
### Real
```
https://verifactu.conecta365.com/api/generateVerifactu?token=XXXXX
```
### Pruebas
```
https://verifactu.dspyme.com/public/api/generateVerifactu?token=XXXXX
```

### Respuestas típicas
Token correcto:
```json
{
    "success": true,
    "message": "Facturas generadas 5"
}
```
Token incorrecto:
```json
{
    "success": false,
    "message": "Token incorrecto"
}
```

---

## 7. Resumen rápido del flujo
1. Busca facturas pendientes.
2. Calcula datos de factura anterior.
3. Genera XML.
4. Agrupa XML.
5. Carga certificado del emisor.
6. Envía SOAP a AEAT.
7. Interpreta respuesta.
8. Actualiza estado.
9. Guarda logs.

---


