<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Darvis\UblPeppol\UblBeBis3Service;

include '../test_data.php';

try {
    // Initialize UBL service
    $ubl = new UblBeBis3Service();

    // Create the document and add all components
    $ubl->createDocument()
        ->addInvoiceHeader(
            $invoice['header']['invoice_number'],
            $invoice['header']['issue_date'],
            $invoice['header']['due_date']
        )
        ->addBuyerReference($invoice['header']['buyer_reference'])
        ->addOrderReference($invoice['header']['order_reference'])
        ->addAdditionalDocumentReference('UBL.BE', 'CommercialInvoice')
        ->addAdditionalDocumentReference('PEPPOL', 'PEPPOLInvoice')
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
            $invoice['customer']['registration_number'],
            $invoice['customer']['contact_name'],
            $invoice['customer']['contact_phone'],
            $invoice['customer']['contact_email']
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

    // --- Start of Calculations ---

    // Calculate line totals and taxes from invoice lines
    $lineTotal = 0;
    $taxAmount = 0;
    foreach ($invoice['lines'] as $line) {
        $lineQuantity = (float)$line['quantity'];
        $linePrice = (float)$line['price_amount'];
        $lineTaxRate = (float)$line['tax_percent'] / 100;

        $lineTotal += $linePrice * $lineQuantity;
        $taxAmount += ($linePrice * $lineQuantity) * $lineTaxRate;
    }

    // Add allowance/charge amount
    $chargeAmount = 25.00; // Fixed charge amount
    $chargeTax = $chargeAmount * 0.21; // 21% VAT on charge
    $totalTax = $taxAmount + $chargeTax;

    // Calculate final monetary totals
    $lineExtensionAmount = round($lineTotal, 2);
    $taxExclusiveAmount = round($lineTotal + $chargeAmount, 2);
    $taxInclusiveAmount = round($taxExclusiveAmount + $totalTax, 2);
    $payableAmount = round($taxInclusiveAmount, 2);

    // --- End of Calculations ---


    // --- Start of UBL Document Assembly ---

    // Add allowance or charge (e.g., insurance fee)
    $ubl->addAllowanceCharge(
        true,                           // isCharge (true for charge, false for allowance)
        $chargeAmount,                  // amount
        'Insurance fee',                // reason
        'S',                            // tax category ID (S = standard rate)
        21.0,                           // tax percentage
        'EUR'                           // currency
    );

    // Add tax subtotals with proper breakdown
    $ubl->addTaxTotal([
        [
            'taxable_amount' => number_format($lineTotal + $chargeAmount, 2, '.', ''),
            'tax_amount' => number_format($totalTax, 2, '.', ''),
            'currency' => 'EUR',
            'tax_category_id' => 'S',     // Standard rate
            'tax_category_name' => 'Standard rate', // Belgian requirement
            'tax_percent' => 21.0,        // 21% VAT
            'tax_scheme_id' => 'VAT',     // VAT tax scheme
        ]
    ]);

    // Add legal monetary total with properly rounded values
    $ubl->addLegalMonetaryTotal(
        [
            'line_extension_amount' => number_format($lineExtensionAmount, 2, '.', ''),
            'tax_exclusive_amount' => number_format($taxExclusiveAmount, 2, '.', ''),
            'tax_inclusive_amount' => number_format($taxInclusiveAmount, 2, '.', ''),
            'charge_total_amount' => number_format($chargeAmount, 2, '.', ''),
            'payable_amount' => number_format($payableAmount, 2, '.', '')
        ],
        'EUR'
    );

    // Add invoice lines (AFTER totals)
    foreach ($invoice['lines'] as $line) {
        $lineTotalAmount = bcmul($line['quantity'], $line['price_amount'], 2);

        $lineData = [
            'id' => $line['id'],
            'quantity' => $line['quantity'],
            'unit_code' => $line['unit_code'],
            'line_extension_amount' => $lineTotalAmount,
            'description' => $line['description'],
            'name' => $line['name'],
            'price_amount' => $line['price_amount'],
            'currency' => 'EUR',
            'accounting_cost' => $line['accounting_cost'] ?? null,
            'order_line_id' => $line['order_line_id'] ?? null,
            'tax_category_id' => $line['tax_category_id'] ?? 'S',
            'tax_category_name' => $line['tax_category_name'] ?? null,
            'tax_percent' => $line['tax_percent'],
            'tax_scheme_id' => $line['tax_scheme_id'] ?? 'VAT',
        ];

        $ubl->addInvoiceLine($lineData);
    }

    // Generate and output the XML
    $xml = $ubl->generateXml();

    // Handle SAPI type: command line saves file, browser interaction serves XML
    if (php_sapi_name() === 'cli') {
        // Save the XML to a file when run from CLI
        $outputFile = __DIR__ . '/invoice.xml';
        file_put_contents($outputFile, $xml);
        echo "Invoice XML generated and saved to: " . $outputFile . "\n";
    } else {
        // Handle download if requested via browser
        if (isset($_GET['download'])) {
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="be-invoice-' . date('Y-m-d_His') . '.xml"');
            header('Content-Length: ' . strlen($xml));
            echo $xml;
            exit;
        }

        // Output to browser with proper content type
        header('Content-Type: application/xml');
        echo $xml;
    }
} catch (\InvalidArgumentException $e) {
    header('Content-Type: text/plain');
    die('Validation error: ' . $e->getMessage());
} catch (\Exception $e) {
    header('Content-Type: text/plain');
    die('Error: ' . $e->getMessage() .
        "\n\nStack trace:\n" . $e->getTraceAsString());
}
