<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Darvis\UblPeppol\UblService;

// Invoice data
$invoice = [
    'invoice_number' => 'INV-2025-001',
    'issue_date' => '2025-09-01',
    'due_date' => '2025-10-01',
    'buyer_reference' => 'CUST-REF-001',
    'order_reference' => 'PO-2025-001',
    'lines' => [
        [
            'id' => '1',
            'quantity' => '2',
            'unit_code' => 'PCE',
            'description' => 'Sample product',
            'name' => 'Product A',
            'price' => '100.00',
            'tax_percent' => '21.00',
            'currency' => 'EUR'
        ],
        [
            'id' => '2',
            'quantity' => '1',
            'unit_code' => 'HUR',
            'description' => 'Consulting',
            'name' => 'Consulting hours',
            'price' => '75.00',
            'tax_percent' => '21.00',
            'currency' => 'EUR'
        ]
    ]
];

try {
    // Initialize UBL service
    $ubl = new UblService();
    
    // Create the document and add all components
    $ubl->createDocument()
        ->addInvoiceHeader(
            $invoice['invoice_number'],
            $invoice['issue_date'],
            $invoice['due_date']
        )
        ->addBuyerReference($invoice['buyer_reference'])
        ->addOrderReference($invoice['order_reference'])
        ->addAccountingSupplierParty()
        ->addAccountingCustomerParty();
    
    // Add invoice lines
    foreach ($invoice['lines'] as $line) {
        $lineTotal = bcmul($line['quantity'], $line['price'], 2);
        
        $ubl->addInvoiceLine(
            $line['id'],
            $line['quantity'],
            $line['unit_code'],
            $lineTotal,
            $line['description'],
            $line['name'],
            $line['price'],
            null, // accounting cost
            null, // order line id
            'ITEM-' . $line['id'], // standard item id
            'NL', // origin country
            'S',  // tax category (S = standard)
            $line['tax_percent']
        );
    }
    
    // Add tax and total calculations
    $ubl->addTaxTotal()
        ->addLegalMonetaryTotal();
    
    // Generate and output the XML
    $xml = $ubl->generateXml();
    
    // Pretty print the XML
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml);
    
    header('Content-Type: application/xml');
    echo $dom->saveXML();
    
} catch (\InvalidArgumentException $e) {
    header('Content-Type: text/plain');
    die('Validation error: ' . $e->getMessage());
} catch (\Exception $e) {
    header('Content-Type: text/plain');
    die('Error: ' . $e->getMessage() . 
        "\n\nStack trace:\n" . $e->getTraceAsString());
}
