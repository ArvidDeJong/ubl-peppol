<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Darvis\UblPeppol\UblService;

// Invoice data
$invoice = [
    'invoice_number' => 'INV-' . date('Ym') . '-001',
    'issue_date' => date('Y-m-d', strtotime('-1 days')),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'buyer_reference' => 'ARVID-REF-001',
    'order_reference' => 'ARVID-PO-2025-001',
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
        ->addAccountingSupplierParty(
            '87654321',                 // Endpoint ID (bijv. KVK-nummer)
            '0106',                     // Endpoint Scheme ID (0106 voor KVK)
            'SUPPLIER-002',             // Interne partij ID
            'Voorbeeld Leverancier B.V.', // Bedrijfsnaam
            'Voorbeeldstraat 42',       // Straat + huisnummer
            '1011 AB',                  // Postcode
            'Utrecht',                  // Plaatsnaam
            'NL',                       // Landcode (2 letters)
            'NL87654321B01'             // BTW-nummer
        )
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

    // Generate the XML
    $xml = $ubl->generateXml();

    // Pretty print the XML
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml);
    $prettyXml = $dom->saveXML();

    // Handle download if requested
    if (isset($_GET['download'])) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="invoice-' . date('Y-m-d') . '.xml"');
        header('Content-Length: ' . strlen($prettyXml));
        echo $prettyXml;
        exit;
    }

    // Output to browser
    header('Content-Type: application/xml');
    echo $prettyXml;
} catch (\InvalidArgumentException $e) {
    header('Content-Type: text/plain');
    die('Validation error: ' . $e->getMessage());
} catch (\Exception $e) {
    header('Content-Type: text/plain');
    die('Error: ' . $e->getMessage() .
        "\n\nStack trace:\n" . $e->getTraceAsString());
}
