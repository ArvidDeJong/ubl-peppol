<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Darvis\UblPeppol\UblService;

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

// Create a new instance of UblService
$ublService = new UblService();

try {
    // 1. Create the document and add required components
    $ublService->createDocument()
        // 2. Add invoice header with basic information
        ->addInvoiceHeader(
            'INV-001',          // Invoice number
            '2025-09-01',       // Issue date
            '2025-10-01'        // Due date
        )
        // 3. Add buyer reference (required for PEPPOL)
        ->addBuyerReference('CUST-REF-001')
        // 4. Add order reference (optional)
        ->addOrderReference('PO-2025-001')
        // 5. Add supplier information
        ->addAccountingSupplierParty()
        // 6. Add customer information
        ->addAccountingCustomerParty()
        // 7. Add delivery information
        ->addDelivery()
        // 8. Add payment means (defaults to SEPA credit transfer)
        ->addPaymentMeans('30') // 30 = Credit transfer
        // 9. Add payment terms
        ->addPaymentTerms()
        // 10. Add allowance/charge (e.g., discount)
        ->addAllowanceCharge()
        // 11. Add first invoice line (positive amount)
        ->addInvoiceLine(
            '1',                        // ID
            '7',                        // Quantity
            'DAY',                      // Unit code (UN/ECE rec 20)
            '2800',                     // Line amount (7 * 400)
            'Consulting services',      // Description
            'Consulting',               // Name
            '400',                      // Price per unit
            'SERVICES',                 // Accounting cost
            'PO-2025-001-1',            // Order line reference
            'SERV-CONSULT-001',         // Standard item ID
            'NL',                       // Origin country
            'S',                        // Tax category (S = standard rate)
            '21.0'                      // Tax percentage (21%)
        )
        // 12. Add second invoice line (negative amount for credit/return)
        ->addInvoiceLine(
            '2',
            '-2',                       // Negative quantity for credit
            'PCE',                      // Pieces
            '-120.00',                  // Negative amount for credit
            'Returned item',
            'Product return',
            '60.00',
            'RETURNS',
            'PO-2025-001-2',
            'PROD-001',
            'BE',
            'S',
            '21.0'
        )
        // 13. Add tax totals (calculated automatically)
        ->addTaxTotal()
        // 14. Add legal monetary totals
        ->addLegalMonetaryTotal();

    // Generate the XML
    $xml = $ublService->generateXml();
    
    // Pretty print the XML for better readability
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml);
    $prettyXml = $dom->saveXML();
    
    // Handle download if requested
    if (isset($_GET['download'])) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="complete-invoice-'.date('Y-m-d').'.xml"');
        header('Content-Length: ' . strlen($prettyXml));
        echo $prettyXml;
        exit;
    }
    
    // Output to browser
    header('Content-Type: application/xml');
    echo $prettyXml;
    
} catch (\Exception $e) {
    echo "Error creating invoice: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous exception: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
