<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Darvis\UblPeppol\UblService;

// Invoice data structure
$invoice = [
    // Invoice header information
    'header' => [
        'invoice_number' => 'INV-' . date('Ym') . '-001',
        'issue_date' => date('Y-m-d', strtotime('-1 days')),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'buyer_reference' => 'ARVID-REF-001',
        'order_reference' => 'ARVID-PO-2025-001',
    ],

    // Supplier information
    'supplier' => [
        'endpoint_id' => '87654321',                // e.g., KVK number
        'endpoint_scheme' => '0106',                // 0106 for KVK
        'party_id' => 'SUPPLIER-002',               // Internal reference
        'name' => 'Darvis ALU',     // Company name
        'street' => 'Koningin Maximalaan 44',           // Street + number
        'postal_code' => '1787DA',                 // Postal code
        'city' => 'Den Helder',                        // City
        'country' => 'NL',                          // Country code (2 letters)
        'vat_number' => 'NL87654321B01',            // VAT number
        'additional_street' => null,                // Optional: additional address line
    ],

    // Customer information
    'customer' => [
        'endpoint_id' => 'DE123456789',             // e.g., VAT number
        'endpoint_scheme' => '0210',                // 0210 for VAT
        'party_id' => 'CUST-' . uniqid(),           // Internal reference
        'name' => 'ARVID.NL B.V.',             // Company name
        'street' => 'Klantstraat 123',              // Street + number
        'postal_code' => '1234 AB',                 // Postal code
        'city' => 'Amsterdam',                      // City
        'country' => 'NL',                          // Country code (2 letters)
        'additional_street' => 'Tweede verdieping', // Optional: additional address line
        'registration_number' => '12345678',        // Optional: company registration number
    ],

    // Invoice lines
    'lines' => [
        [
            'id' => '1',
            'quantity' => '2',
            'unit_code' => 'PCE',                   // UN/ECE rec 20 unit code
            'description' => 'Sample product',
            'name' => 'Product A',
            'price' => '100.00',
            'tax_percent' => '21.00',
            'currency' => 'EUR',
            'accounting_cost' => null,              // Optional: accounting cost center
            'order_line_id' => null,                // Optional: reference to order line
        ],
        [
            'id' => '2',
            'quantity' => '1',
            'unit_code' => 'HUR',                   // Hours
            'description' => 'Consulting services',
            'name' => 'Consulting hours',
            'price' => '75.00',
            'tax_percent' => '21.00',
            'currency' => 'EUR',
            'accounting_cost' => 'PROJ-001',        // Optional: project code
            'order_line_id' => 'PO-2023-456',       // Optional: purchase order line reference
        ]
    ],

    // Delivery information
    'delivery' => [
        'date' => date('Y-m-d', strtotime('+1 day')), // Leveringsdatum (morgen)
        'location_id' => 'DELIVERY-' . uniqid(),     // Uniek ID voor de leveringslocatie
        'location_scheme' => '0088',                 // 0088 = GLN
        'street' => 'Aambeeld 20',              // Straatnaam
        'additional_street' => 'Tav. Ontvangst',     // Aanvullende straatinformatie
        'city' => 'Medemblik',                       // Stad
        'postal_code' => '1011 AA',                  // Postcode
        'country' => 'NL',                           // Landcode (2 letters)
        'party_name' => 'ARVID.NL B.V.'              // Naam ontvangende partij
    ],

    // Payment information
    'payment' => [
        'means_code' => '30',                       // 30 = Credit transfer
        'means_name' => 'Credit transfer via SEPA', // Payment method description
        'payment_id' => 'INV-' . date('Y') . '-123', // Payment reference
        'account_iban' => 'NL71ABNA0607005106',     // IBAN
        'account_name' => 'Darvis ALU',             // Account holder name
        'bic' => 'ABNANL2A',                        // BIC/SWIFT code
        'channel_code' => 'IBAN',                   // Payment channel
        'due_date' => date('Y-m-d', strtotime('+30 days')), // Payment due date
        'terms' => [
            'note' => 'Binnen 14 dagen betalen met 2% korting, anders binnen 30 dagen',
            'discount_percent' => '2.00',
            'discount_date' => date('Y-m-d', strtotime('+14 days')),
            'discount_amount' => null, // Dit zou automatisch berekend kunnen worden, maar is hier niet geïmplementeerd
        ],
    ],
];

try {
    // Initialize UBL service
    $ubl = new UblService();

    // Create the document and add all components
    $ubl->createDocument()
        ->addInvoiceHeader(
            $invoice['header']['invoice_number'],
            $invoice['header']['issue_date'],
            $invoice['header']['due_date']
        )
        ->addBuyerReference($invoice['header']['buyer_reference'])
        ->addOrderReference($invoice['header']['order_reference'])
        ->addAccountingSupplierParty(
            $invoice['supplier']['endpoint_id'],
            $invoice['supplier']['endpoint_scheme'],
            $invoice['supplier']['party_id'],
            $invoice['supplier']['name'],
            $invoice['supplier']['street'],
            $invoice['supplier']['postal_code'],
            $invoice['supplier']['city'],
            $invoice['supplier']['country'],
            $invoice['supplier']['vat_number'],
            $invoice['supplier']['additional_street']
        )
        ->addAccountingCustomerParty(
            $invoice['customer']['endpoint_id'],
            $invoice['customer']['endpoint_scheme'],
            $invoice['customer']['party_id'],
            $invoice['customer']['name'],
            $invoice['customer']['street'],
            $invoice['customer']['postal_code'],
            $invoice['customer']['city'],
            $invoice['customer']['country'],
            $invoice['customer']['additional_street'],
            $invoice['customer']['registration_number']
        )
        ->addDelivery(
            $invoice['delivery']['date'],
            $invoice['delivery']['location_id'],
            $invoice['delivery']['location_scheme'],
            $invoice['delivery']['street'],
            $invoice['delivery']['additional_street'],
            $invoice['delivery']['city'],
            $invoice['delivery']['postal_code'],
            $invoice['delivery']['country'],
            $invoice['delivery']['party_name']
        )
        ->addPaymentMeans(
            $invoice['payment']['means_code'],
            $invoice['payment']['means_name'],
            $invoice['payment']['payment_id'],
            $invoice['payment']['account_iban'],
            $invoice['payment']['account_name'],
            $invoice['payment']['bic'],
            $invoice['payment']['channel_code'],
            $invoice['payment']['due_date']
        )
        ->addPaymentTerms(
            $invoice['payment']['terms']['note'],
            $invoice['payment']['terms']['discount_percent'],
            $invoice['payment']['terms']['discount_amount'],
            $invoice['payment']['terms']['discount_date']
        );

    // Add invoice lines
    foreach ($invoice['lines'] as $line) {
        $lineTotal = bcmul($line['quantity'], $line['price'], 2);

        $lineData = [
            'id' => $line['id'],
            'quantity' => $line['quantity'],
            'unit_code' => $line['unit_code'],
            'line_extension_amount' => $lineTotal,
            'description' => $line['description'],
            'name' => $line['name'],
            'price_amount' => $line['price'],
            'currency' => 'EUR',
            'accounting_cost' => $line['accounting_cost'],
            'order_line_id' => $line['order_line_id'],
            'standard_item_id' => null,  // Optioneel: standaard item ID (bijv. GTIN)
            'origin_country' => 'NL',    // Optioneel: land van herkomst (2-letterige code)
            'tax_category_id' => 'S',    // BTW categorie (S = standaardtarief)
            'tax_percent' => $line['tax_percent'],
            'tax_scheme_id' => 'VAT',    // BTW-schema (VAT = BTW)
            'item_type_code' => '1000',   // Productcategorie code (CPV)
            'item_type_description' => 'Product' // Producttype omschrijving
        ];

        $ubl->addInvoiceLine($lineData);
    }

    // Add allowance or charge (e.g., insurance fee)
    $ubl->addAllowanceCharge(
        true,                           // isCharge (true for charge, false for allowance)
        25.00,                          // amount
        'Insurance fee',                // reason
        'S',                            // tax category ID (S = standard rate)
        21.0,                           // tax percentage
        'EUR'                           // currency
    );

    // Add tax and total calculations
    $lineTotal = 100.00;  // Sum of all line items before tax
    $taxAmount = 21.00;   // 21% of 100
    $chargeAmount = 25.00; // Total charges
    
    $ubl->addTaxTotal([
        [
            'taxable_amount' => $lineTotal,
            'tax_amount' => $taxAmount,
            'currency' => 'EUR',
            'tax_category_id' => 'S',     // Standard rate
            'tax_percent' => 21.0,        // 21% VAT
            'tax_scheme_id' => 'VAT'      // VAT tax scheme
        ]
    ])->addLegalMonetaryTotal(
        [
            'line_extension_amount' => $lineTotal,
            'tax_exclusive_amount' => $lineTotal + $chargeAmount,
            'tax_inclusive_amount' => $lineTotal + $chargeAmount + $taxAmount,
            'charge_total_amount' => $chargeAmount,
            'payable_amount' => $lineTotal + $chargeAmount + $taxAmount
        ],
        'EUR'
    );

    // Generate and output the XML
    $xml = $ubl->generateXml();

    // Handle download if requested
    if (isset($_GET['download'])) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="invoice-' . date('Y-m-d') . '.xml"');
        header('Content-Length: ' . strlen($xml));
        echo $xml;
        exit;
    }

    // Output to browser with proper content type
    header('Content-Type: application/xml');
    echo $xml;
} catch (\InvalidArgumentException $e) {
    header('Content-Type: text/plain');
    die('Validation error: ' . $e->getMessage());
} catch (\Exception $e) {
    header('Content-Type: text/plain');
    die('Error: ' . $e->getMessage() .
        "\n\nStack trace:\n" . $e->getTraceAsString());
}
