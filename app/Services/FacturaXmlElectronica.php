<?php

namespace App\Services;

use App\Models\Facturas;
use DOMDocument;

class FacturaXmlElectronica
{
    public function generateXml(Facturas $factura)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Nodo raíz con namespace FE
        $root = $dom->createElementNS(
            'http://www.facturae.es/Facturae/2014/v3.2.1/Facturae',
            'fe:Facturae'
        );
        // ds namespace (firma) por compatibilidad
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $dom->appendChild($root);

        //
        // FILE HEADER
        //
        $fileHeader = $dom->createElement('FileHeader');
        $fileHeader->appendChild($dom->createElement('SchemaVersion', '3.2.1'));
        $fileHeader->appendChild($dom->createElement('Modality', 'I'));
        $fileHeader->appendChild($dom->createElement('InvoiceIssuerType', 'EM'));

        $batch = $dom->createElement('Batch');
        // BatchIdentifier construido a partir del CIF/NIF emisor y la serie (solo con campos que existen)
        $batchIdentifier = (string) ($factura->cifEmisor ?? $factura->idEmisorFactura ?? '');
        $batchIdentifier .= ($factura->serie ?? '');
        $batch->appendChild($dom->createElement('BatchIdentifier', $batchIdentifier));
        $batch->appendChild($dom->createElement('InvoicesCount', '1'));

        $totalInvoicesAmount = $dom->createElement('TotalInvoicesAmount');
        $totalInvoicesAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->importeTotal)));
        $batch->appendChild($totalInvoicesAmount);

        $totalOutstandingAmount = $dom->createElement('TotalOutstandingAmount');
        $totalOutstandingAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->importeTotal)));
        $batch->appendChild($totalOutstandingAmount);

        $totalExecutableAmount = $dom->createElement('TotalExecutableAmount');
        $totalExecutableAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->importeTotal)));
        $batch->appendChild($totalExecutableAmount);

        $batch->appendChild($dom->createElement('InvoiceCurrencyCode', 'EUR'));
        $fileHeader->appendChild($batch);
        $root->appendChild($fileHeader);

        //
        // PARTIES (solo campos presentes en la migración)
        //
        $parties = $dom->createElement('Parties');

        // SellerParty (DATOS INVENTADOS)
        $seller = $dom->createElement('SellerParty');
        $taxIdS = $dom->createElement('TaxIdentification');
        $taxIdS->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdS->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        $taxIdS->appendChild($dom->createElement('TaxIdentificationNumber', $factura->idEmisorFactura));
        $seller->appendChild($taxIdS);

        $legalS = $dom->createElement('LegalEntity');
        $legalS->appendChild($dom->createElement('CorporateName', $factura->nombreEmisor ?? ''));

        // Dirección del emisor (rellena con valores fijos o genéricos si no tienes en DB)
        $addressS = $dom->createElement('AddressInSpain');
        $addressS->appendChild($dom->createElement('Address', 'c/ Alcala, 137')); // <-- fijo o configurable
        $addressS->appendChild($dom->createElement('PostCode', '28001'));
        $addressS->appendChild($dom->createElement('Town', 'Madrid'));
        $addressS->appendChild($dom->createElement('Province', 'Madrid'));
        $addressS->appendChild($dom->createElement('CountryCode', 'ESP'));
        $legalS->appendChild($addressS);

        $seller->appendChild($legalS);
        $parties->appendChild($seller);

        // BuyerParty (DATOS INVENTADOS EN EL BUYERPARTY NORMAL)
        $buyer = $dom->createElement('BuyerParty');
        $taxIdB = $dom->createElement('TaxIdentification');
        $taxIdB->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdB->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        $taxIdB->appendChild($dom->createElement('TaxIdentificationNumber', $factura->idEmisorFactura));
        $buyer->appendChild($taxIdB);

        // Bloque si es un organismo público
        if (!empty($factura->oficontable) && !empty($factura->orggestor) && !empty($factura->utramitadora)) {
            $adminCentres = $dom->createElement('AdministrativeCentres');

            // Oficina Contable
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura->oficontable));
            $centre->appendChild($dom->createElement('RoleTypeCode', '01')); // Es 01

            $address = $dom->createElement('AddressInSpain');
            $address->appendChild($dom->createElement('Address', 'Marqués de Arneva, 1'));
            $address->appendChild($dom->createElement('PostCode', '03300'));
            $address->appendChild($dom->createElement('Town', 'ORIHUELA'));
            $address->appendChild($dom->createElement('Province', 'ALICANTE'));
            $address->appendChild($dom->createElement('CountryCode', $factura->codigoPais ?? 'ESP'));
            $centre->appendChild($address);

            $centre->appendChild($dom->createElement('CentreDescription', 'OFICINA CONTABLE'));
            $adminCentres->appendChild($centre);
            //------------------------
            // Órgano Gestor
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura->orggestor));
            $centre->appendChild($dom->createElement('RoleTypeCode', '02')); // Es 02

            $address = $dom->createElement('AddressInSpain');
            $address->appendChild($dom->createElement('Address', 'Marqués de Arneva, 1'));
            $address->appendChild($dom->createElement('PostCode', '03300'));
            $address->appendChild($dom->createElement('Town', 'ORIHUELA'));
            $address->appendChild($dom->createElement('Province', 'ALICANTE'));
            $address->appendChild($dom->createElement('CountryCode', $factura->codigoPais ?? 'ESP'));
            $centre->appendChild($address);

            $centre->appendChild($dom->createElement('CentreDescription', 'ORGANO GESTOR'));
            $adminCentres->appendChild($centre);
            //------------------------
            // Unidad Tramitadora
            $centre = $dom->createElement('AdministrativeCentre');
            $centre->appendChild($dom->createElement('CentreCode', $factura->utramitadora));
            $centre->appendChild($dom->createElement('RoleTypeCode', '03'));

            $address = $dom->createElement('AddressInSpain');
            $address->appendChild($dom->createElement('Address', 'Marqués de Arneva, 1'));
            $address->appendChild($dom->createElement('PostCode', '03300'));
            $address->appendChild($dom->createElement('Town', 'ORIHUELA'));
            $address->appendChild($dom->createElement('Province', 'ALICANTE'));
            $address->appendChild($dom->createElement('CountryCode', 'ESP'));
            $centre->appendChild($address);

            $centre->appendChild($dom->createElement('CentreDescription', 'UNIDAD TRAMITADORA'));
            $adminCentres->appendChild($centre);

            $buyer->appendChild($adminCentres);
        }

        $legalB = $dom->createElement('LegalEntity');
        $legalB->appendChild($dom->createElement('CorporateName', $factura->nombreCliente ?? ''));

        // Dirección del cliente (lo mismo: fijo o configurable)
        $addressB = $dom->createElement('AddressInSpain');
        $addressB->appendChild($dom->createElement('Address', 'c/ San Vicente, 1'));
        $addressB->appendChild($dom->createElement('PostCode', '41008'));
        $addressB->appendChild($dom->createElement('Town', 'Sevilla'));
        $addressB->appendChild($dom->createElement('Province', 'Sevilla'));
        $addressB->appendChild($dom->createElement('CountryCode', $factura->codigoPais ?? 'ESP'));
        $legalB->appendChild($addressB);

        $contact = $dom->createElement('ContactDetails');
        $contact->appendChild($dom->createElement('ElectronicMail', $factura->emailCliente ?? ''));
        $legalB->appendChild($contact);

        $buyer->appendChild($legalB);

        // Final de bloques de organismo público
        $parties->appendChild($buyer);

        $root->appendChild($parties);

        // INVOICES
        $invoices = $dom->createElement('Invoices');
        $invoice = $dom->createElement('Invoice');

        // InvoiceHeader
        $header = $dom->createElement('InvoiceHeader');
        $header->appendChild($dom->createElement('InvoiceNumber', $factura->numFactura ?? ''));
        $header->appendChild($dom->createElement('InvoiceSeriesCode', $factura->serie ?? ''));
        $header->appendChild($dom->createElement('InvoiceDocumentType', 'FC'));
        $header->appendChild($dom->createElement('InvoiceClass', 'OO'));
        $invoice->appendChild($header);

        // InvoiceIssueData
        $issue = $dom->createElement('InvoiceIssueData');
        $issue->appendChild(
            $dom->createElement('IssueDate', $this->formatDate($factura->fechaExpedicionFactura ?? $factura->fechaOperacion ?? ''))
        );
        $issue->appendChild($dom->createElement('InvoiceCurrencyCode', 'EUR'));
        $issue->appendChild($dom->createElement('TaxCurrencyCode', 'EUR'));
        $issue->appendChild($dom->createElement('LanguageName', 'es'));
        $invoice->appendChild($issue);

        // TaxesOutputs (resumido con los campos existentes en la factura)
        $taxesOutputs = $dom->createElement('TaxesOutputs');
        $tax = $dom->createElement('Tax');
        $tax->appendChild($dom->createElement('TaxTypeCode', '01'));
        $tax->appendChild($dom->createElement('TaxRate', $this->formatAmount($factura->tipoImpositivo ?? 0)));

        $taxableBase = $dom->createElement('TaxableBase');
        $taxableBase->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $tax->appendChild($taxableBase);

        $taxAmount = $dom->createElement('TaxAmount');
        $taxAmount->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->cuotaRepercutida ?? 0)));
        $tax->appendChild($taxAmount);

        $taxesOutputs->appendChild($tax);
        $invoice->appendChild($taxesOutputs);

        // InvoiceTotals
        $totals = $dom->createElement('InvoiceTotals');
        $totals->appendChild($dom->createElement('TotalGrossAmount', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $totals->appendChild($dom->createElement('TotalGeneralDiscounts', $this->formatAmount(0)));
        $totals->appendChild($dom->createElement('TotalGeneralSurcharges', $this->formatAmount(0)));
        $totals->appendChild($dom->createElement('TotalGrossAmountBeforeTaxes', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $totals->appendChild($dom->createElement('TotalTaxOutputs', $this->formatAmount($factura->cuotaRepercutida ?? 0)));
        $totals->appendChild($dom->createElement('TotalTaxesWithheld', $this->formatAmount(0)));
        $totals->appendChild($dom->createElement('InvoiceTotal', $this->formatAmount($factura->importeTotal ?? 0)));
        $totals->appendChild($dom->createElement('TotalOutstandingAmount', $this->formatAmount($factura->importeTotal ?? 0)));
        $totals->appendChild($dom->createElement('TotalExecutableAmount', $this->formatAmount($factura->importeTotal ?? 0)));
        $totals->appendChild($dom->createElement('TotalReimbursableExpenses', $this->formatAmount(0)));
        $invoice->appendChild($totals);

        // Items: si no tienes una relación "lineas" en la tabla usaremos la descripcionOperacion
        $items = $dom->createElement('Items');
        $line = $dom->createElement('InvoiceLine');
        $line->appendChild($dom->createElement('ItemDescription', $factura->descripcionOperacion ?? ''));
        $line->appendChild($dom->createElement('Quantity', $this->formatAmount(1)));
        $line->appendChild($dom->createElement('UnitOfMeasure', '01'));
        $line->appendChild($dom->createElement('UnitPriceWithoutTax', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $line->appendChild($dom->createElement('TotalCost', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $line->appendChild($dom->createElement('GrossAmount', $this->formatAmount($factura->baseImponibleACoste ?? 0)));

        $taxOutputsLine = $dom->createElement('TaxesOutputs');
        $taxLine = $dom->createElement('Tax');
        $taxLine->appendChild($dom->createElement('TaxTypeCode', '01'));
        $taxLine->appendChild($dom->createElement('TaxRate', $this->formatAmount($factura->tipoImpositivo ?? 0)));

        $taxableBaseLine = $dom->createElement('TaxableBase');
        $taxableBaseLine->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->baseImponibleACoste ?? 0)));
        $taxLine->appendChild($taxableBaseLine);

        $taxAmountLine = $dom->createElement('TaxAmount');
        $taxAmountLine->appendChild($dom->createElement('TotalAmount', $this->formatAmount($factura->cuotaRepercutida ?? 0)));
        $taxLine->appendChild($taxAmountLine);

        $taxOutputsLine->appendChild($taxLine);
        $line->appendChild($taxOutputsLine);
        $items->appendChild($line);
        $invoice->appendChild($items);

        $invoices->appendChild($invoice);
        $root->appendChild($invoices);

        return $dom->saveXML();
    }

    private function formatAmount($value)
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }

        $date = trim($date);

        // Intentamos formatos comunes
        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'd-m-Y',
            'd/m/Y',
            'd.m.Y',
            'd M Y',
            'Y/m/d'
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $date);
            if ($dt && $dt->format($fmt) === $date) {
                return $dt->format('Y-m-d');
            }
        }

        // Último recurso: strtotime (tolerante con varios formatos)
        $ts = strtotime(str_replace('/', '-', $date));
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        // Si no se puede parsear, devolvemos el original (puede fallar al validar)
        return $date;
    }
}
