<?php

namespace App\Services;

use DOMDocument;

class FacturaXmlElectronica
{
    /**
     * Genera el XML Facturae 3.2.1 a partir de los datos del request.
     *
     * @param array $factura Datos generales (emisor, receptor, totales, fechas…).
     * @param array $lineas  Líneas de detalle (Descripcion, Cantidad, etc.).
     */
    public function generateXml(array $factura, array $lineas): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // <fe:Facturae ...>
        $root = $dom->createElementNS(
            'http://www.facturae.es/Facturae/2014/v3.2.1/Facturae',
            'fe:Facturae'
        );
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:ds',
            'http://www.w3.org/2000/09/xmldsig#'
        );
        $dom->appendChild($root);

        //
        // FILE HEADER
        //
        $fileHeader = $dom->createElement('FileHeader');
        $fileHeader->appendChild($dom->createElement('SchemaVersion', '3.2.1'));
        $fileHeader->appendChild($dom->createElement('Modality', 'I'));
        $fileHeader->appendChild($dom->createElement('InvoiceIssuerType', 'EM'));

        $batch = $dom->createElement('Batch');
        $batchIdentifier =
            ($factura['CifEmisor'] ?? '') .
            ($factura['NumFactura'] ?? '') .
            ($factura['Serie'] ?? '');
        $batch->appendChild($dom->createElement('BatchIdentifier', $batchIdentifier));
        $batch->appendChild($dom->createElement('InvoicesCount', '1'));

        // Totales principales desde el JSON
        $total = $this->formatAmount($factura['TotalFactura'] ?? 0);

        $totalInvoicesAmount = $dom->createElement('TotalInvoicesAmount');
        $totalInvoicesAmount->appendChild($dom->createElement('TotalAmount', $total));
        $batch->appendChild($totalInvoicesAmount);

        $totalOutstandingAmount = $dom->createElement('TotalOutstandingAmount');
        $totalOutstandingAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura['TotalPendienteCobro'] ?? $total)));
        $batch->appendChild($totalOutstandingAmount);

        $totalExecutableAmount = $dom->createElement('TotalExecutableAmount');
        $totalExecutableAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura['TotalPendienteCobro'] ?? $total)));
        $batch->appendChild($totalExecutableAmount);

        $batch->appendChild($dom->createElement('InvoiceCurrencyCode', 'EUR'));
        $fileHeader->appendChild($batch);
        $root->appendChild($fileHeader);

        //
        // PARTIES
        //
        $parties = $dom->createElement('Parties');

        // SellerParty (emisor)
        $seller = $dom->createElement('SellerParty');
        $taxIdS = $dom->createElement('TaxIdentification');
        $taxIdS->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdS->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        $taxIdS->appendChild($dom->createElement('TaxIdentificationNumber', $factura['CifEmisor'] ?? ''));
        $seller->appendChild($taxIdS);

        $legalS = $dom->createElement('LegalEntity');
        $legalS->appendChild($dom->createElement('CorporateName', $factura['NombreEmisor'] ?? ''));
        $legalS->appendChild($dom->createElement('TradeName', $factura['NombreEmisor'] ?? ''));

        $addressS = $dom->createElement('AddressInSpain');
        $addressS->appendChild($dom->createElement('Address', $factura['EmisorDirec'] ?? ''));
        $addressS->appendChild($dom->createElement('PostCode', $factura['EmisorCpostal'] ?? ''));
        $addressS->appendChild($dom->createElement('Town', $factura['EmisorCiudad'] ?? ''));
        $addressS->appendChild($dom->createElement('Province', $factura['EmisorProv'] ?? ''));
        $addressS->appendChild($dom->createElement('CountryCode', $factura['EmisorCpais'] ?? 'ESP'));
        $legalS->appendChild($addressS);

        /* (Opcional) datos de contacto emisor si algún día se añade al JSON
            if (!empty($factura['EmisorTelefono']) || !empty($factura['EmisorWeb']) || !empty($factura['EmisorEmail'])) {
            $contactS = $dom->createElement('ContactDetails');
            if (!empty($factura['EmisorTelefono'])) {
                $contactS->appendChild($dom->createElement('Telephone', $factura['EmisorTelefono']));
            }
            if (!empty($factura['EmisorWeb'])) {
                $contactS->appendChild($dom->createElement('WebAddress', $factura['EmisorWeb']));
            }
            if (!empty($factura['EmisorEmail'])) {
                $contactS->appendChild($dom->createElement('ElectronicMail', $factura['EmisorEmail']));
            }
            $legalS->appendChild($contactS);
            }
            
            if (!empty($factura['EmailCliente'])) {
            $contactB = $dom->createElement('ContactDetails');
            $contactB->appendChild($dom->createElement('ElectronicMail', $factura['EmailCliente']));
            $legalB->appendChild($contactB);
            }*/

        if (!empty($factura['EmisorTelefono']) || !empty($factura['EmisorWeb']) || !empty($factura['EmisorEmail'])) {
            $contactS = $dom->createElement('ContactDetails');
            if (!empty($factura['EmisorTelefono'])) {
                $contactS->appendChild($dom->createElement('Telephone', $factura['EmisorTelefono']));
            }
            if (!empty($factura['EmisorWeb'])) {
                $contactS->appendChild($dom->createElement('WebAddress', $factura['EmisorWeb']));
            }
            if (!empty($factura['EmisorEmail'])) {
                $contactS->appendChild($dom->createElement('ElectronicMail', $factura['EmisorEmail']));
            }
            $legalS->appendChild($contactS);
        }

        $seller->appendChild($legalS);
        $parties->appendChild($seller);

        // BuyerParty (cliente / destinatario)
        $buyer = $dom->createElement('BuyerParty');
        $taxIdB = $dom->createElement('TaxIdentification');
        $taxIdB->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdB->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        $taxIdB->appendChild($dom->createElement('TaxIdentificationNumber', $factura['NifCliente'] ?? ''));
        $buyer->appendChild($taxIdB);

        // Centros administrativos si es organismo público
        if (!empty($factura['Oficontable']) && !empty($factura['Orggestor']) && !empty($factura['Utramitadora'])) {
            $adminCentres = $dom->createElement('AdministrativeCentres');

            // Oficina Contable
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura['Oficontable']));
            $centre->appendChild($dom->createElement('RoleTypeCode', '01'));
            $addr = $dom->createElement('AddressInSpain');
            $addr->appendChild($dom->createElement('Address', $factura['OficontableDirec'] ?? ''));
            $addr->appendChild($dom->createElement('PostCode', $factura['OficontableCpostal'] ?? ''));
            $addr->appendChild($dom->createElement('Town', $factura['OficontableCiudad'] ?? ''));
            $addr->appendChild($dom->createElement('Province', $factura['OficontableProv'] ?? ''));
            $addr->appendChild($dom->createElement('CountryCode', $factura['OficontableCpais'] ?? 'ESP'));
            $centre->appendChild($addr);
            $centre->appendChild($dom->createElement('CentreDescription', 'OFICINA CONTABLE'));
            $adminCentres->appendChild($centre);

            // Órgano Gestor
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura['Orggestor']));
            $centre->appendChild($dom->createElement('RoleTypeCode', '02'));
            $addr = $dom->createElement('AddressInSpain');
            $addr->appendChild($dom->createElement('Address', $factura['OrggestorDirec'] ?? ''));
            $addr->appendChild($dom->createElement('PostCode', $factura['OrggestorCpostal'] ?? ''));
            $addr->appendChild($dom->createElement('Town', $factura['OrggestorCiudad'] ?? ''));
            $addr->appendChild($dom->createElement('Province', $factura['OrggestorProv'] ?? ''));
            $addr->appendChild($dom->createElement('CountryCode', $factura['OrggestorCpais'] ?? 'ESP'));
            $centre->appendChild($addr);
            $centre->appendChild($dom->createElement('CentreDescription', 'ORGANO GESTOR'));
            $adminCentres->appendChild($centre);

            // Unidad Tramitadora
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura['Utramitadora']));
            $centre->appendChild($dom->createElement('RoleTypeCode', '03'));
            $addr = $dom->createElement('AddressInSpain');
            $addr->appendChild($dom->createElement('Address', $factura['UtramitadoraDirec'] ?? ''));
            $addr->appendChild($dom->createElement('PostCode', $factura['UtramitadoraCpostal'] ?? ''));
            $addr->appendChild($dom->createElement('Town', $factura['UtramitadoraCiudad'] ?? ''));
            $addr->appendChild($dom->createElement('Province', $factura['UtramitadoraProv'] ?? ''));
            $addr->appendChild($dom->createElement('CountryCode', $factura['UtramitadoraCpais'] ?? 'ESP'));
            $centre->appendChild($addr);
            $centre->appendChild($dom->createElement('CentreDescription', 'UNIDAD TRAMITADORA'));
            $adminCentres->appendChild($centre);

            $buyer->appendChild($adminCentres);
        }

        $legalB = $dom->createElement('LegalEntity');
        $legalB->appendChild($dom->createElement('CorporateName', $factura['NombreCliente'] ?? ''));

        $addressB = $dom->createElement('AddressInSpain');
        $addressB->appendChild($dom->createElement('Address', $factura['ReceptorDirec'] ?? ''));
        $addressB->appendChild($dom->createElement('PostCode', $factura['ReceptorCpostal'] ?? ''));
        $addressB->appendChild($dom->createElement('Town', $factura['ReceptorCiudad'] ?? ''));
        $addressB->appendChild($dom->createElement('Province', $factura['ReceptorProv'] ?? ''));
        $addressB->appendChild($dom->createElement('CountryCode', $factura['ReceptorCpais'] ?? 'ESP'));
        $legalB->appendChild($addressB);

        if (!empty($factura['EmailCliente'])) {
            $contactB = $dom->createElement('ContactDetails');
            $contactB->appendChild($dom->createElement('ElectronicMail', $factura['EmailCliente']));
            $legalB->appendChild($contactB);
        }

        $buyer->appendChild($legalB);
        $parties->appendChild($buyer);
        $root->appendChild($parties);

        //
        // INVOICES
        //
        $invoices = $dom->createElement('Invoices');
        $invoice  = $dom->createElement('Invoice');

        // InvoiceHeader
        $header = $dom->createElement('InvoiceHeader');
        $header->appendChild($dom->createElement('InvoiceNumber', $factura['NumFactura'] ?? ''));
        $header->appendChild($dom->createElement('InvoiceSeriesCode', $factura['Serie'] ?? ''));
        $header->appendChild($dom->createElement('InvoiceDocumentType', 'FC'));
        $header->appendChild($dom->createElement('InvoiceClass', 'OO'));
        $invoice->appendChild($header);

        // InvoiceIssueData
        $issue = $dom->createElement('InvoiceIssueData');
        $issue->appendChild(
            $dom->createElement(
                'IssueDate',
                $this->formatDate($factura['FechaExpedicionFactura'] ?? ($factura['FechaOperacion'] ?? ''))
            )
        );
        $issue->appendChild($dom->createElement('InvoiceCurrencyCode', 'EUR'));
        $issue->appendChild($dom->createElement('TaxCurrencyCode', 'EUR'));
        $issue->appendChild($dom->createElement('LanguageName', 'es'));
        $invoice->appendChild($issue);

        // TaxesOutputs (resumen)
        $taxesOutputs = $dom->createElement('TaxesOutputs');
        $tax = $dom->createElement('Tax');
        $tax->appendChild($dom->createElement('TaxTypeCode', '01'));

        // Tipo de IVA global desde la primera línea
        $tipoIvaGlobal = 0;
        if (!empty($lineas) && isset($lineas[0]['TipoIva'])) {
            $tipoIvaGlobal = $lineas[0]['TipoIva'];
        }
        $tax->appendChild($dom->createElement('TaxRate', $this->formatAmount($tipoIvaGlobal)));

        $base  = $this->formatAmount($factura['TotalBaseImponible'] ?? 0);
        $cuota = $this->formatAmount($factura['TotalImpuestosRepercutidos'] ?? 0);

        $taxableBase = $dom->createElement('TaxableBase');
        $taxableBase->appendChild($dom->createElement('TotalAmount', $base));
        $tax->appendChild($taxableBase);

        $taxAmount = $dom->createElement('TaxAmount');
        $taxAmount->appendChild($dom->createElement('TotalAmount', $cuota));
        $tax->appendChild($taxAmount);

        $taxesOutputs->appendChild($tax);
        $invoice->appendChild($taxesOutputs);

        // InvoiceTotals
        $totals = $dom->createElement('InvoiceTotals');
        $totals->appendChild($dom->createElement(
            'TotalGrossAmount',
            $this->formatAmount($factura['TotalImporteBruto'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalGeneralDiscounts',
            $this->formatAmount($factura['TotalDescuentosGenerales'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalGeneralSurcharges',
            $this->formatAmount($factura['TotalRecargosGenerales'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalGrossAmountBeforeTaxes',
            $this->formatAmount($factura['TotalBaseImponible'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalTaxOutputs',
            $this->formatAmount($factura['TotalImpuestosRepercutidos'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalTaxesWithheld',
            $this->formatAmount($factura['TotalImpuestosRetenidos'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'InvoiceTotal',
            $this->formatAmount($factura['TotalFactura'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalOutstandingAmount',
            $this->formatAmount($factura['TotalPendienteCobro'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalExecutableAmount',
            $this->formatAmount($factura['TotalPendienteCobro'] ?? 0)
        ));
        $totals->appendChild($dom->createElement(
            'TotalReimbursableExpenses',
            $this->formatAmount(0)
        ));
        $invoice->appendChild($totals);

        //
        // Items (múltiples líneas tipo cu158.xml)
        //
        $items = $dom->createElement('Items');

        foreach ($lineas as $linea) {
            $line = $dom->createElement('InvoiceLine');
            $line->appendChild($dom->createElement('ItemDescription', $linea['Descripcion'] ?? ''));
            $line->appendChild($dom->createElement('Quantity', $this->formatAmount($linea['Cantidad'] ?? 0)));
            $line->appendChild($dom->createElement('UnitOfMeasure', $linea['UnidadMedida'] ?? '01'));

            $unitPrice = $this->formatAmount($linea['PrecioUnitarioSinIva'] ?? 0);
            $baseLinea = $this->formatAmount($linea['BaseImponible'] ?? 0);

            $line->appendChild($dom->createElement('UnitPriceWithoutTax', $unitPrice));
            $line->appendChild($dom->createElement('TotalCost', $baseLinea));
            $line->appendChild($dom->createElement('GrossAmount', $baseLinea));

            $taxOutputsLine = $dom->createElement('TaxesOutputs');
            $taxLine = $dom->createElement('Tax');
            $taxLine->appendChild($dom->createElement('TaxTypeCode', '01'));
            $taxLine->appendChild($dom->createElement('TaxRate', $this->formatAmount($linea['TipoIva'] ?? 0)));

            $taxableBaseLine = $dom->createElement('TaxableBase');
            $taxableBaseLine->appendChild($dom->createElement('TotalAmount', $baseLinea));
            $taxLine->appendChild($taxableBaseLine);

            $taxAmountLine = $dom->createElement('TaxAmount');
            $taxAmountLine->appendChild($dom->createElement(
                'TotalAmount',
                $this->formatAmount($linea['CuotaIva'] ?? 0)
            ));
            $taxLine->appendChild($taxAmountLine);

            $taxOutputsLine->appendChild($taxLine);
            $line->appendChild($taxOutputsLine);

            $items->appendChild($line);
        }

        $invoice->appendChild($items);

        if (!empty($factura['Notas'])) {
            $additionalData = $dom->createElement('AdditionalData');
            $additionalData->appendChild(
                $dom->createElement('InvoiceAdditionalInformation', $factura['Notas'])
            );
            $invoice->appendChild($additionalData);
        }

        $invoices->appendChild($invoice);
        $root->appendChild($invoices);

        return $dom->saveXML();
    }

    private function formatAmount($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function formatDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        $date = trim((string)$date);

        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'd-m-Y',
            'd/m/Y',
            'd.m.Y',
            'd M Y',
            'Y/m/d',
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $date);
            if ($dt && $dt->format($fmt) === $date) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime(str_replace('/', '-', $date));
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return $date;
    }
}
