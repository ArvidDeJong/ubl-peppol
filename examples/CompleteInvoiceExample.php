<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Darvis\UblPeppol\UblNLBis3Service;

/**
 * Complete UBL Invoice Example
 * 
 * This example demonstrates how to create a complete UBL invoice document with:
 * - Invoice header with basic information
 * - Buyer and supplier information
 * - Multiple invoice lines (including a credit line)
 * - Tax and total calculations
 * - Payment terms and means
 */

try {
    // Create a new instance of UblNLBis3Service
    $UblNLBis3Service = new UblNLBis3Service();

    // Set the standard based on a query parameter (for demonstration)
    // 'peppol' for Belgian standard (default)
    // 'si_ubl' for Dutch standard
    $standard = $_GET['standard'] ?? 'peppol';
    if ($standard === 'si_ubl') {
        $UblNLBis3Service->setStandard(UblNLBis3Service::STANDARD_SI_UBL);
    } else {
        $UblNLBis3Service->setStandard(UblNLBis3Service::STANDARD_PEPPOL);
    }
    // --- Data Definition ---
    $invoiceLinesData = [
        [
            'id' => '1',
            'quantity' => 7,
            'unit_code' => 'DAY',
            'description' => 'Consulting services',
            'name' => 'Consulting',
            'price_amount' => 400,
            'currency' => 'EUR',
            'accounting_cost' => 'SERVICES',
            'order_line_id' => 'PO-2025-001-1',
            'tax_category_id' => 'S',
            'tax_percent' => 21.0,
            'tax_category_name' => 'Standaard tarief',
        ],
        [
            'id' => '2',
            'quantity' => -2,
            'unit_code' => 'PCE',
            'description' => 'Returned item',
            'name' => 'Product return',
            'price_amount' => 60.00,
            'currency' => 'EUR',
            'accounting_cost' => 'RETURNS',
            'order_line_id' => 'PO-2025-001-2',
            'tax_category_id' => 'S',
            'tax_percent' => 21.0,
            'tax_category_name' => 'Standaard tarief',
        ],
    ];

    // --- Calculations ---
    $lineExtensionAmount = 0;
    $taxExclusiveAmount = 0;
    $totalTaxAmount = 0;
    $taxes = [];

    foreach ($invoiceLinesData as $line) {
        $lineTotal = $line['quantity'] * $line['price_amount'];
        $lineExtensionAmount += $lineTotal;

        $taxPercent = $line['tax_percent'];
        $taxAmount = $lineTotal * ($taxPercent / 100);
        $totalTaxAmount += $taxAmount;

        if (!isset($taxes[$taxPercent])) {
            $taxes[$taxPercent] = [
                'taxable_amount' => 0,
                'tax_amount' => 0,
                'currency' => $line['currency'],
                'tax_category_id' => $line['tax_category_id'],
                'tax_category_name' => $line['tax_category_name'],
                'tax_percent' => $taxPercent,
                'tax_scheme_id' => 'VAT',
            ];
        }
        $taxes[$taxPercent]['taxable_amount'] += $lineTotal;
        $taxes[$taxPercent]['tax_amount'] += $taxAmount;
    }

    $taxExclusiveAmount = $lineExtensionAmount; // Assuming no document-level allowances/charges
    $taxInclusiveAmount = $lineExtensionAmount + $totalTaxAmount;
    $payableAmount = $taxInclusiveAmount;

    $monetaryTotals = [
        'line_extension_amount' => $lineExtensionAmount,
        'tax_exclusive_amount' => $taxExclusiveAmount,
        'tax_inclusive_amount' => $taxInclusiveAmount,
        'charge_total_amount' => 0, // No charges in this example
        'payable_amount' => $payableAmount,
    ];

    // --- UBL Document Generation ---
    $UblNLBis3Service->createDocument()
        ->addInvoiceHeader('INV-001', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+29 days')))
        ->addBuyerReference('CUST-REF-001')
        ->addOrderReference('PO-2025-001')
        ->addAccountingSupplierParty(
            '12345678',
            '0106',
            'SUPPLIER-001',
            'Leverancier B.V.',
            'Kerkstraat 1',
            '1234 AB',
            'Amsterdam',
            'NL',
            'NL123456789B01',
            'Tweede verdieping'
        )
        ->addAccountingCustomerParty(
            'NL987654321B01',       // Endpoint ID (e.g., VAT number)
            '0210',                 // Scheme ID
            'CUST-001',             // Internal party ID
            'Klant Bedrijf B.V.',   // Company name
            'Klantstraat 123',      // Street address
            '1234 AB',              // Postal code
            'Amsterdam',            // City
            'NL',                   // Country code (2 letters)
            'Tweede verdieping',    // Additional address line (optional)
            '12345678'              // Company registration number (optional)
        )
        ->addDelivery(
            date('Y-m-d'),
            'DELIVERY-12345',
            '0088',
            'Bezorgstraat 10',
            'Tweede verdieping',
            'Amsterdam',
            '1011 AB',
            'NL',
            'ARVID.NL B.V.'
        )
        ->addPaymentMeans(
            '30',                           // PaymentMeansCode
            'Credit transfer',              // PaymentMeansName
            'Factuur INV-001',              // PaymentID
            'NL88RABO0123456789',           // AccountID (IBAN)
            'Leverancier B.V.',             // AccountName
            'RABONL2U'                      // FinancialInstitutionID (BIC)
        )
        ->addPaymentTerms('Betaling binnen 30 dagen');

    // Add invoice lines
    foreach ($invoiceLinesData as $lineData) {
        $UblNLBis3Service->addInvoiceLine($lineData);
    }

    // Add totals
    $UblNLBis3Service->addTaxTotal(array_values($taxes))
        ->addLegalMonetaryTotal($monetaryTotals, 'EUR');

    // Generate the XML
    $xml = $UblNLBis3Service->generateXml();

    // Pretty print the XML for better readability
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml);
    $prettyXml = $dom->saveXML();

    // Handle download if requested
    if (isset($_GET['download'])) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="complete-invoice-' . date('Y-m-d') . '.xml"');
        header('Content-Length: ' . strlen($prettyXml));
        echo $prettyXml;
        exit;
    }

    // Output to browser
    header('Content-Type: application/xml');
    echo $prettyXml;
} catch (\Throwable $e) {
    // Display a user-friendly error message
    file_put_contents('php://stderr', "Error creating invoice: " . $e->getMessage() . "\n");
    file_put_contents('php://stderr', $e->getTraceAsString() . "\n");
    exit(1);
}
