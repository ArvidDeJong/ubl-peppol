<?php

/**
 * Writes one sample document per case into examples/validate/out/, for the check against an
 * official validator that CLAUDE.md demands before a release that changes the generated XML.
 *
 *   php examples/validate/generate_samples.php
 *
 * Upload the Dutch files to https://test.peppolautoriteit.nl/validate and the Belgian files to
 * https://ecosio.com/en/peppol-and-xml-document-validator/ . See CONTRIBUTING.md.
 *
 * The lines, the payment and the delivery come from the invented data in examples/nl/test_data.php
 * and examples/be/test_data.php. The Belgian parties are defined here, because the Belgian data
 * file describes Dutch companies and a validator checks the checksum of a 0208 enterprise number.
 */

require_once __DIR__.'/../../vendor/autoload.php';

use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;
use Darvis\UblPeppol\Validation\InvoiceValidationResult;
use Darvis\UblPeppol\Vat\VatCategory;
use Darvis\UblPeppol\Vat\VatExemptionReason;

/**
 * @return array<string, mixed>
 */
function sampleData(string $country): array
{
    $invoice = [];
    include __DIR__.'/../'.$country.'/test_data.php';

    return $invoice;
}

/**
 * Lines, VAT and totals for the lines of a data file, with an optional document level discount,
 * charge and prepayment. Everything is in one VAT category: the standard rate of 21%, or 0% for any
 * other category.
 *
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array<string, string>  $exemption  tax_exemption_reason_code and tax_exemption_reason, when the category takes one
 * @return array{lines: array<int, array<string, mixed>>, tax: array<int, array<string, mixed>>, totals: array<string, float>}
 */
function sampleAmounts(array $lines, float $allowance = 0.0, float $charge = 0.0, float $prepaid = 0.0, string $category = 'S', array $exemption = []): array
{
    $percent = $category === 'S' ? 21.0 : 0.0;

    $lineData = [];
    $lineTotal = 0.0;

    foreach ($lines as $line) {
        $amount = round((float) $line['quantity'] * (float) $line['price_amount'], 2);
        $lineTotal += $amount;

        $lineData[] = [
            'id' => $line['id'],
            'quantity' => $line['quantity'],
            'unit_code' => $line['unit_code'],
            'line_extension_amount' => $amount,
            'description' => $line['description'],
            'name' => $line['name'],
            'price_amount' => $line['price_amount'],
            'currency' => 'EUR',
            'order_line_id' => $line['order_line_id'] ?? null,
            'tax_category_id' => $category,
            'tax_percent' => $percent,
            'tax_scheme_id' => 'VAT',
        ];
    }

    $taxable = round($lineTotal - $allowance + $charge, 2);
    $tax = round($taxable * $percent / 100, 2);

    return [
        'lines' => $lineData,
        'tax' => [$exemption + [
            'taxable_amount' => $taxable,
            'tax_amount' => $tax,
            'currency' => 'EUR',
            'tax_category_id' => $category,
            'tax_percent' => $percent,
            'tax_scheme_id' => 'VAT',
        ]],
        'totals' => [
            'line_extension_amount' => round($lineTotal, 2),
            'tax_exclusive_amount' => $taxable,
            'tax_inclusive_amount' => round($taxable + $tax, 2),
            'allowance_total_amount' => $allowance,
            'charge_total_amount' => $charge,
            'prepaid_amount' => $prepaid,
            'payable_amount' => round($taxable + $tax - $prepaid, 2),
        ],
    ];
}

/**
 * A Dutch invoice up to and including the payment terms.
 *
 * @param  array<string, mixed>  $data
 */
function dutchInvoice(array $data, string $number): UblNlBis3Service
{
    $ubl = (new UblNlBis3Service)
        ->createDocument()
        ->addInvoiceHeader($number, $data['header']['issue_date'], $data['header']['due_date']);

    return dutchReferencesAndParties($ubl, $data);
}

/**
 * The references, the parties and the payment of a Dutch document, invoice or credit note.
 *
 * @param  array<string, mixed>  $data
 */
function dutchReferencesAndParties(UblNlBis3Service $ubl, array $data): UblNlBis3Service
{
    $supplier = $data['supplier'];
    $customer = $data['customer'];
    $payment = $data['payment'];

    return $ubl
        ->addBuyerReference($data['header']['buyer_reference'])
        ->addOrderReference($data['header']['order_reference'])
        ->addAccountingSupplierParty(
            $supplier['endpoint_id'], $supplier['endpoint_scheme'], $supplier['party_id'], $supplier['name'],
            $supplier['street'], $supplier['postal_code'], $supplier['city'], $supplier['country'], $supplier['vat_number']
        )
        // BT-30: the KvK number of the supplier, scheme 0106 (NL-R-003)
        ->addSupplierLegalRegistration($supplier['kvk_number'])
        ->addAccountingCustomerParty(
            $customer['endpoint_id'], $customer['endpoint_scheme'], $customer['party_id'], $customer['name'],
            $customer['street'], $customer['postal_code'], $customer['city'], $customer['country'],
            null, $customer['registration_number'], null, null, null, $customer['vat_number']
        )
        ->addPaymentMeans($payment['means_code'], $payment['means_name'], $payment['payment_id'], $payment['account_iban'], $payment['account_name'], $payment['bic'])
        ->addPaymentTerms($payment['terms']['note']);
}

/**
 * The Belgian parties, with enterprise numbers that pass the mod 97 check of scheme 0208.
 */
function belgianParties(UblBeBis3Service $ubl): UblBeBis3Service
{
    return $ubl
        ->addAccountingSupplierParty(
            '0681845662', '0208', '0681845662', 'Voorbeeld Leverancier BV', 'Grote Markt 1', '1000', 'Brussel', 'BE', 'BE0681845662'
        )
        ->addAccountingCustomerParty(
            '0999000228', '0208', '0999000228', 'Voorbeeld Klant NV', 'Kerkstraat 123', '2000', 'Antwerpen', 'BE',
            null, '0999000228', null, null, null, 'BE0999000228'
        );
}

$nl = sampleData('nl');
$be = sampleData('be');
$cases = [];

// 1. Dutch invoice, nothing special
$amounts = sampleAmounts($nl['lines']);
$ubl = dutchInvoice($nl, 'SAMPLE-NL-001')->addTaxTotal($amounts['tax'])->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['nl-invoice-plain.xml'] = $ubl;

// 2. Dutch invoice with a document level discount (BT-92, BT-107) and a prepayment (BT-113)
$amounts = sampleAmounts($nl['lines'], allowance: 25.00, prepaid: 100.00);
$ubl = dutchInvoice($nl, 'SAMPLE-NL-002')
    ->addAllowanceCharge(false, 25.00, 'Discount', 'S', 21.0, 'EUR')
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['nl-invoice-discount-prepayment.xml'] = $ubl;

// 3. Dutch invoice with a buyer accounting reference (BT-19)
$amounts = sampleAmounts($nl['lines']);
$ubl = dutchInvoice($nl, 'SAMPLE-NL-003')
    ->addAccountingCost('PROJECT-7')
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['nl-invoice-accounting-cost.xml'] = $ubl;

// 4. Belgian invoice with a document level charge (BT-99, BT-108)
$amounts = sampleAmounts($be['lines'], charge: 25.00);
$ubl = (new UblBeBis3Service)
    ->createDocument()
    ->addInvoiceHeader('SAMPLE-BE-001', $be['header']['issue_date'], $be['header']['due_date'])
    ->addAccountingCost('PROJECT-7')
    ->addBuyerReference($be['header']['buyer_reference'])
    ->addOrderReference($be['header']['order_reference']);
belgianParties($ubl)
    ->addPaymentMeans('30', 'Credit transfer', 'SAMPLE-BE-001', 'BE68539007547034', 'Voorbeeld Leverancier BV', 'BBRUBEBB')
    ->addPaymentTerms('Payment within 30 days')
    ->addAllowanceCharge(true, 25.00, 'Insurance fee', 'S', 21.0, 'EUR')
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['be-invoice-charge.xml'] = $ubl;

// 5. Belgian credit note. The order reference goes in front of the billing reference.
$amounts = sampleAmounts($be['lines']);
$ubl = (new UblBeBis3Service)
    ->createCreditNoteDocument()
    ->addCreditNoteHeader('SAMPLE-BE-CN-001', $be['header']['issue_date'])
    ->addOrderReference($be['header']['order_reference'])
    ->addBillingReference('SAMPLE-BE-001', $be['header']['issue_date']);
belgianParties($ubl)
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addCreditNoteLine($line);
}
$cases['be-credit-note.xml'] = $ubl;

// 6. Dutch credit note with a document level discount. NL-R-001 wants the billing reference, and
//    <CreditNote> has its own element order: the builder sorts it, whatever the order of the calls.
$amounts = sampleAmounts($nl['lines'], allowance: 25.00);
$ubl = (new UblNlBis3Service)
    ->createCreditNoteDocument()
    ->addCreditNoteHeader('SAMPLE-NL-CN-001', $nl['header']['issue_date'])
    ->addBillingReference('SAMPLE-NL-002', $nl['header']['issue_date']);
dutchReferencesAndParties($ubl, $nl)
    ->addAllowanceCharge(false, 25.00, 'Discount', 'S', 21.0, 'EUR')
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addCreditNoteLine($line);
}
$cases['nl-credit-note.xml'] = $ubl;

// 7. Intra-community supply from a Dutch supplier to a Belgian customer, built by the Belgian builder
//    because the builder follows the receiver. Category K gets exemption reason code VATEX-EU-IC
//    without asking (BR-IC-10); BR-IC-11 and BR-IC-12 want the delivery date and country.
$amounts = sampleAmounts($be['lines'], category: 'K');
$supplier = $nl['supplier'];
$ubl = (new UblBeBis3Service)
    ->createDocument()
    ->addInvoiceHeader('SAMPLE-BE-002', $be['header']['issue_date'], $be['header']['due_date'])
    ->addBuyerReference($be['header']['buyer_reference'])
    ->addOrderReference($be['header']['order_reference'])
    ->addAccountingSupplierParty(
        $supplier['endpoint_id'], $supplier['endpoint_scheme'], $supplier['endpoint_id'], $supplier['name'],
        $supplier['street'], $supplier['postal_code'], $supplier['city'], 'NL', $supplier['vat_number']
    )
    ->addAccountingCustomerParty(
        '0999000228', '0208', '0999000228', 'Voorbeeld Klant NV', 'Kerkstraat 123', '2000', 'Antwerpen', 'BE',
        null, '0999000228', null, null, null, 'BE0999000228'
    )
    ->addDelivery($be['header']['issue_date'], '5790000435975', '0088', 'Kerkstraat 123', null, 'Antwerpen', '2000', 'BE')
    ->addPaymentMeans('30', 'Credit transfer', 'SAMPLE-BE-002', $nl['payment']['account_iban'], $supplier['name'], $nl['payment']['bic'])
    ->addPaymentTerms('Payment within 30 days')
    ->addTaxTotal($amounts['tax'])
    ->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['be-invoice-intra-community.xml'] = $ubl;

// 8. Dutch invoice under a domestic reverse charge (category AE), with the Dutch standard text
//    next to the code (BT-120, BT-121, BR-AE-10).
$amounts = sampleAmounts($nl['lines'], category: 'AE', exemption: [
    'tax_exemption_reason_code' => VatExemptionReason::REVERSE_CHARGE,
    'tax_exemption_reason' => (string) VatCategory::ReverseCharge->exemptionReasonText('nl'),
]);
$ubl = dutchInvoice($nl, 'SAMPLE-NL-004')->addTaxTotal($amounts['tax'])->addLegalMonetaryTotal($amounts['totals'], 'EUR');
foreach ($amounts['lines'] as $line) {
    $ubl->addInvoiceLine($line);
}
$cases['nl-invoice-reverse-charge.xml'] = $ubl;

// Write and check
$out = __DIR__.'/out';
if (! is_dir($out) && ! mkdir($out, 0777, true)) {
    fwrite(STDERR, "Could not create {$out}\n");
    exit(1);
}

$failed = false;

foreach ($cases as $file => $builder) {
    /** @var InvoiceValidationResult $result */
    $result = $builder->validate();
    $xml = $builder->generateXml();

    $wellFormed = (new DOMDocument)->loadXML($xml);
    file_put_contents($out.'/'.$file, $xml);

    $ok = $wellFormed && $result->isValid();
    $failed = $failed || ! $ok;

    echo str_pad($file, 40).($wellFormed ? 'well formed' : 'NOT WELL FORMED').'   validate(): '.($result->isValid() ? 'valid' : 'INVALID').PHP_EOL;

    foreach (array_merge($result->errors, $result->warnings) as $message) {
        echo '    '.$message.PHP_EOL;
    }
}

echo PHP_EOL.'Written to '.$out.PHP_EOL;

exit($failed ? 1 : 0);
