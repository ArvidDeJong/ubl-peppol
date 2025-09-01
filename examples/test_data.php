<?php

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
        'vat_number' => 'NL853848932B01',            // VAT number
        'additional_street' => null,                // Optional: additional address line
    ],

    // Customer information
    'customer' => [
        'vat_number' => '853848932B01',              // Customer VAT number without country code
        'endpoint_id' => 'NL853848932B01',           // VAT number with country code
        'endpoint_scheme' => '0210',                // 0210 for VAT
        'party_id' => 'CUST-' . uniqid(),           // Internal reference
        'name' => 'ARVID.NL B.V.',                  // Company name
        'street' => 'Klantstraat 123',              // Street + number
        'postal_code' => '1234 AB',                 // Postal code
        'city' => 'Amsterdam',                      // City
        'country' => 'NL',                          // Country code (2 letters)
        'additional_street' => 'Tweede verdieping', // Optional: additional address line
        'registration_number' => 'NL853848932B01',        // Optional: company registration number
        'contact_name' => 'John Doe',               // Contact person name
        'contact_phone' => '+31 20 123 4567',       // Contact phone number
        'contact_email' => 'john.doe@example.com',  // Contact email
    ],

    // Invoice lines
    'lines' => [
        [
            'id' => '1',
            'quantity' => '2',
            'unit_code' => 'C62',                   // UN/ECE rec 20 unit code for 'piece'
            'description' => 'Sample product',
            'name' => 'Product A',
            'price_amount' => '100.00',
            'tax_percent' => '21.00',
            'currency' => 'EUR',
            'accounting_cost' => 'PROJ-001',              // Optional: accounting cost center
            'order_line_id' => 'PO-2023-123',       // Optional: reference to order line
            'standard_item_id' => null,  // Optioneel: standaard item ID (bijv. GTIN)
            'origin_country' => 'NL',    // Optioneel: land van herkomst (2-letterige code)
            'tax_category_id' => 'S',    // BTW categorie (S = standaardtarief)
            'tax_scheme_id' => 'VAT',    // BTW-schema (VAT = BTW)
            'item_type_code' => '1000',   // Product category code
            'item_type_scheme' => 'STD',  // Standard classification scheme (from UNTDID 7143 list)
            'item_type_description' => 'Product' // Product type description
        ],
        [
            'id' => '2',
            'quantity' => '1',
            'unit_code' => 'HUR',                   // Hours
            'description' => 'Consulting services',
            'name' => 'Consulting hours',
            'price_amount' => '75.00',
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
        'account_iban' => 'NL32RABO0180732595',     // IBAN
        'account_name' => 'Darvis ALU',             // Account holder name
        'bic' => 'RABONL22',                        // BIC/SWIFT code
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
