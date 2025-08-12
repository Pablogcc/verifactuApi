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

        // SellerParty
        $seller = $dom->createElement('SellerParty');
        $taxIdS = $dom->createElement('TaxIdentification');
        $taxIdS->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdS->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        // usamos cifEmisor si existe, si no idEmisorFactura
        $taxIdS->appendChild($dom->createElement('TaxIdentificationNumber', $factura->cifEmisor ?? $factura->idEmisorFactura));
        $seller->appendChild($taxIdS);

        $legalS = $dom->createElement('LegalEntity');
        $legalS->appendChild($dom->createElement('CorporateName', $factura->nombreEmisor ?? ''));
        $seller->appendChild($legalS);
        $parties->appendChild($seller);

        // BuyerParty
        $buyer = $dom->createElement('BuyerParty');
        $taxIdB = $dom->createElement('TaxIdentification');
        $taxIdB->appendChild($dom->createElement('PersonTypeCode', 'J'));
        $taxIdB->appendChild($dom->createElement('ResidenceTypeCode', 'R'));
        $taxIdB->appendChild($dom->createElement('TaxIdentificationNumber', $factura->nifCliente ?? ''));
        $buyer->appendChild($taxIdB);

        $legalB = $dom->createElement('LegalEntity');
        $legalB->appendChild($dom->createElement('CorporateName', $factura->nombreCliente ?? ''));
        $buyer->appendChild($legalB);
        $parties->appendChild($buyer);

        $root->appendChild($parties);

        //
        // INVOICES
        //
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
        $issue->appendChild($dom->createElement('IssueDate', $factura->fechaExpedicionFactura ?? $factura->fechaOperacion ?? ''));
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
}
