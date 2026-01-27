## Raíz del documento

**fe:Facturae**  
Elemento raíz del XML Facturae (con el namespace de la versión de esquema que uses).

---

## 1) FileHeader — Cabecera del fichero/lote

Bloque con la información general del fichero (y del lote).

- **SchemaVersion**  
  Versión de la especificación/estructura Facturae utilizada (p.ej. 3.2 o 3.2.1).

- **Modality**  
  Modalidad de facturación del fichero (por ejemplo, individual).

- **InvoiceIssuerType**  
  Indica el tipo de emisor de la factura dentro del fichero (p. ej. emisor vs. destinatario en casos particulares). En la guía se menciona expresamente en relación con facturas “expedidas por el destinatario”.

### 1.1) Batch — Datos del lote

Identifica el lote de facturas incluido en el fichero.

- **BatchIdentifier**  
  Identificador único del lote (en tu caso lo construyes como CIF+Nº+Serie).

- **InvoicesCount**  
  Número de facturas incluidas en el lote. (Si metes 10 `<Invoice>`, aquí debe ir 10).

- **TotalInvoicesAmount / TotalAmount**  
  Total monetario del lote (importe total del conjunto de facturas).

- **TotalOutstandingAmount / TotalAmount**  
  Total pendiente de pago del lote (lo que queda por cobrar/pagar globalmente).

- **TotalExecutableAmount / TotalAmount**  
  Importe “ejecutable” del lote (en muchos escenarios coincide con el pendiente).

- **InvoiceCurrencyCode**  
  Divisa del lote/facturas (código ISO 4217 alpha-3, p.ej. EUR).

---

## 2) Parties — Partes (emisor y receptor)

Agrupa los datos del emisor (**SellerParty**) y del receptor/cliente (**BuyerParty**).

### 2.1) SellerParty — Emisor

**TaxIdentification**  
Identificación fiscal del emisor.

- **PersonTypeCode**  
  Tipo de persona (por ejemplo: jurídica vs. física).

- **ResidenceTypeCode**  
  Tipo de residencia fiscal.

- **TaxIdentificationNumber**  
  NIF/CIF del sujeto (con reglas de composición según administración y, si aplica, prefijos de país en operaciones intracomunitarias).

**LegalEntity**  
Datos legales cuando el emisor es una entidad.

- **CorporateName**  
  Razón social.

- **TradeName**  
  Nombre comercial.

**AddressInSpain**  
Dirección nacional (España).

- **Address**  
  Dirección completa (tipo de vía, nombre, número, piso…).

- **PostCode**  
  Código postal.

- **Town**  
  Población (asociada al CP).

- **Province**  
  Provincia.

- **CountryCode**  
  Código ISO 3166-1 alpha-3; si es domicilio en España será ESP.

**ContactDetails** (opcional)  
Datos de contacto.

- **Telephone** – Teléfono.  
- **WebAddress** – Web/URL.  
- **ElectronicMail** – Email.

### 2.2) BuyerParty — Receptor / Cliente

**TaxIdentification**  
Identificación fiscal del receptor (mismos subcampos que en emisor):

- **PersonTypeCode**, **ResidenceTypeCode**, **TaxIdentificationNumber**.

**AdministrativeCentres** (opcional)  
Se usa cuando el receptor es un organismo público y necesitas indicar centros administrativos (p. ej., FACe/DIR3).

Dentro creas uno o varios:

**AdministrativeCentre**

- **CentreCode**  
  Código del centro (habitualmente DIR3 cuando aplica).

- **RoleTypeCode**  
  Código de rol del centro (en tu caso usas 01, 02, 03).

- **AddressInSpain**  
  Dirección del centro (misma estructura de dirección en España).

- **CentreDescription**  
  Texto descriptivo del centro (p.ej. “OFICINA CONTABLE”, “ORGANO GESTOR”, “UNIDAD TRAMITADORA”).

**LegalEntity**  
Datos legales del receptor.

- **CorporateName**  
  Razón social del cliente/receptor.

**AddressInSpain**  
Dirección del receptor (misma semántica de Address, PostCode, Town, Province, CountryCode).

**ContactDetails** (opcional)  
En tu caso lo usas para email:

- **ElectronicMail** – Correo electrónico.

---

## 3) Invoices — Conjunto de facturas del fichero

**Invoices**  
Contenedor del conjunto de facturas del fichero.

**Invoice**  
Una factura. En Facturae pueden existir una o más facturas dentro de `Invoices`.

### 3.1) InvoiceHeader — Identificación de la factura

Bloque que identifica inequívocamente la factura.

- **InvoiceNumber**  
  Número de factura asignado por el emisor.

- **InvoiceSeriesCode**  
  Serie asignada por el emisor (opcional).

- **InvoiceDocumentType**  
  Tipo de documento de factura. Puede tomar valores `FC`, `FA`, `AF` (descrito como completa/ordinaria, simplificada, etc.).

- **InvoiceClass**  
  Clase de factura (`OO`, `OR`, `OC`, `CO`, `CR`, `CC`: original, rectificativa, recapitulativa, duplicados…).

### 3.2) InvoiceIssueData — Emisión, fechas, idioma y divisas

- **IssueDate**  
  Fecha de expedición (ISO 8601). Es la fecha con efectos fiscales y no podrá ser posterior a la fecha de firma electrónica.

**InvoicingPeriod** (opcional)  
Periodo de facturación: se usa cuando aplica (servicio prestado temporalmente o factura recapitulativa) y la guía indica que será obligatoria si `InvoiceClass` es `OC` o `CC`.

- **StartDate** – Fecha de inicio (ISO 8601).  
- **EndDate** – Fecha fin (ISO 8601).

- **InvoiceCurrencyCode**  
  Moneda de la operación (ISO 4217 alpha-3).

- **TaxCurrencyCode**  
  Moneda del impuesto (ISO 4217 alpha-3).

- **LanguageName**  
  Lengua del documento (ISO 639-1 alpha-2, p.ej. `es`).

### 3.3) TaxesOutputs — Impuestos repercutidos (resumen)

**TaxesOutputs**  
Conjunto de impuestos repercutidos.

**Tax**  
Un impuesto dentro del resumen.

- **TaxTypeCode**  
  Identificador del impuesto. Si no corresponde a los códigos definidos, se usa `05` (“otro”) y se identifica el impuesto adicionalmente según la guía.

- **TaxRate**  
  Tipo impositivo (ojo: no siempre son porcentajes; depende del impuesto).

- **TaxableBase**  
  Base imponible del impuesto.  
  - **TotalAmount** – Importe (en la moneda de facturación; típicamente con 2 decimales).

- **TaxAmount**  
  Cuota del impuesto.  
  - **TotalAmount** – Importe de la cuota.

### 3.4) InvoiceTotals — Totales de la factura

Bloque con los totales globales de la factura.

- **TotalGrossAmount**  
  Total Importe Bruto (suma de importes brutos de los detalles).

- **TotalGeneralDiscounts**  
  Total general de descuentos (sumatorio de descuentos a nivel factura).

- **TotalGeneralSurcharges**  
  Total general de recargos/cargos a nivel factura.

- **TotalGrossAmountBeforeTaxes**  
  Total bruto antes de impuestos = TotalGrossAmount - TotalGeneralDiscounts + TotalGeneralSurcharges.

- **TotalTaxOutputs**  
  Total impuestos repercutidos (sumatorio).

- **TotalTaxesWithheld**  
  Total impuestos retenidos (si aplica).

- **InvoiceTotal**  
  Total factura = TotalGrossAmountBeforeTaxes + TotalTaxOutputs - TotalTaxesWithheld.

- **TotalOutstandingAmount**  
  Total a pagar (pendiente).

- **TotalExecutableAmount**  
  Total ejecutable (habitualmente coincide con el pendiente, dependiendo del caso).

- **TotalReimbursableExpenses**  
  Total de suplidos.

### 3.5) Items — Líneas de factura

**Items**  
Contenedor de líneas/detalle.

**InvoiceLine**  
Una línea de factura.

- **ItemDescription**  
  Descripción del bien o servicio.

- **Quantity**  
  Cantidad (número de unidades servidas/prestadas).

- **UnitOfMeasure**  
  Unidad de medida de la cantidad (códigos recomendados UN/CEFACT; p.ej. 01, 02…).

- **UnitPriceWithoutTax**  
  Precio unitario sin impuestos, en la moneda indicada en cabecera; siempre sin impuestos y (según guía) se refleja con 6 decimales en el formato numérico.

- **TotalCost**  
  Coste total de la línea = Quantity × UnitPriceWithoutTax (en la guía aparece con formato de 6 decimales).

- **GrossAmount**  
  Importe bruto de línea. En la guía aparece como resultado: TotalCost - DiscountAmount + ChargeAmount (con formato de 6 decimales).

**TaxesOutputs** (dentro de `InvoiceLine`)  
Resumen de impuestos a nivel de línea (misma estructura conceptual que el resumen global):

- **Tax** / **TaxTypeCode**, **TaxRate**, **TaxableBase**/**TotalAmount**, **TaxAmount**/**TotalAmount**.

### 3.6) AdditionalData — Información adicional (opcional)

**AdditionalData**  
Bloque para datos adicionales.

- **InvoiceAdditionalInformation**  
  Texto de información adicional de la factura (tú lo usas para Notas).

---

## Notas rápidas de coherencia

- Si incluyes varias facturas en un XML, ajusta `InvoicesCount` y los totales del `Batch`.
- `InvoicingPeriod` no es “siempre”: se usa cuando toca (servicios en periodo / recapitulativas) y puede ser obligatorio según `InvoiceClass`.
- Formatos numéricos importantes en líneas:
  - `UnitPriceWithoutTax` y `TotalCost` suelen ir con 6 decimales en la guía.
  - `GrossAmount` se define con la fórmula basada en coste, descuentos y cargos.
