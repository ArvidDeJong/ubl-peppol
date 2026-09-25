<?php

declare(strict_types=1);

use Darvis\UblPeppol\Models\PeppolLog;
use Darvis\UblPeppol\PeppolService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The peppol_logs table is opt-in: it only exists after the host app published and ran the
 * migration. Sending has to work without it, and has to log when it is there.
 */
beforeEach(function () {
    config([
        'ubl-peppol.url' => 'https://access-point.test/send',
        'ubl-peppol.username' => 'user',
        'ubl-peppol.password' => 'secret',
    ]);

    Http::preventStrayRequests();
});

function createPeppolLogsTable(): void
{
    $migration = require __DIR__.'/../../database/migrations/create_peppol_logs_table.php.stub';
    $migration->up();
}

it('sends a document without the log table', function () {
    Http::fake(['access-point.test/*' => Http::response(['id' => 'abc'], 200)]);

    expect(Schema::hasTable('peppol_logs'))->toBeFalse();

    $result = (new PeppolService)->sendUblXml('<Invoice/>', 'INV-1');

    expect($result['success'])->toBeTrue()
        ->and($result['status_code'])->toBe(200)
        ->and($result['log_id'])->toBeNull();

    Http::assertSentCount(1);
});

it('sends an invoice model without the log table', function () {
    Http::fake(['access-point.test/*' => Http::response(['id' => 'abc'], 200)]);

    $invoice = (object) ['id' => 7, 'invoice_nr' => 'INV-7'];

    $result = (new PeppolService)->sendInvoice($invoice, '<Invoice/>');

    expect($result['success'])->toBeTrue()
        ->and($result['log_id'])->toBeNull();
});

it('reports a rejected document without the log table', function () {
    Http::fake(['access-point.test/*' => Http::response('rejected', 422)]);

    $result = (new PeppolService)->sendUblXml('<Invoice/>', 'INV-1');

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(422)
        ->and($result['error'])->toBe('rejected')
        ->and($result['log_id'])->toBeNull();
});

it('writes a log row when the log table exists', function () {
    createPeppolLogsTable();
    Http::fake(['access-point.test/*' => Http::response(['id' => 'abc'], 200)]);

    $result = (new PeppolService)->sendUblXml('<Invoice/>', 'INV-1');

    $log = PeppolLog::sole();

    expect($result['log_id'])->toBe($log->id)
        ->and($log->status)->toBe('success')
        ->and($log->invoice_nr)->toBe('INV-1')
        ->and($log->http_status_code)->toBe(200);
});

it('logs a failure when the log table exists', function () {
    createPeppolLogsTable();
    Http::fake(['access-point.test/*' => Http::response('rejected', 422)]);

    $invoice = (object) ['id' => 7, 'invoice_nr' => 'INV-7'];

    $result = (new PeppolService)->sendInvoice($invoice, '<Invoice/>');

    $log = PeppolLog::sole();

    expect($result['log_id'])->toBe($log->id)
        ->and($log->status)->toBe('error')
        ->and((int) $log->invoice_id)->toBe(7)
        ->and($log->error)->toBe('rejected');
});

it('cleans up nothing, and says why, without the log table', function () {
    $this->artisan('peppol:cleanup')
        ->expectsOutputToContain('peppol_logs')
        ->assertSuccessful();
});

it('reports a successful send as a success when the invoice model has no peppol_sent_at column', function () {
    Schema::create('host_invoices', function ($table) {
        $table->id();
        $table->string('invoice_nr');
    });

    $invoice = new class extends Model
    {
        protected $table = 'host_invoices';

        public $timestamps = false;

        protected $guarded = [];
    };
    $invoice = $invoice->create(['invoice_nr' => 'INV-8']);

    Http::fake(['access-point.test/*' => Http::response(['id' => 'abc'], 200)]);

    $result = (new PeppolService)->sendInvoice($invoice, '<Invoice/>');

    expect($result['success'])->toBeTrue()
        ->and($result['status_code'])->toBe(200)
        ->and($result['warning'])->toStartWith('The invoice was sent, but peppol_sent_at could not be set on the model');
});

it('keeps the response body out of the application log, because it can hold invoice data', function () {
    Http::fake(['access-point.test/*' => Http::response(['customer' => 'Secret Customer BV'], 200)]);
    Log::spy();

    (new PeppolService)->sendUblXml('<Invoice/>', 'INV-1');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context = []) => $message === 'Peppol: Response received' && ! array_key_exists('response', $context));
});
