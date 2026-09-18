<?php

declare(strict_types=1);

/**
 * The package promises that invoices can be built without Laravel: `composer require` pulls in
 * nothing but PHP and the DOM extension. Only the files listed below may reach for Illuminate.
 * A `use Illuminate\...` that creeps into a builder or the validator breaks that promise for
 * every plain-PHP user, and nothing else in the suite would notice.
 */
const LARAVEL_LAYER = [
    'Console/CleanupPeppolLogsCommand.php',
    'Models/PeppolLog.php',
    'PeppolService.php',
    'UblPeppolServiceProvider.php',
];

function sourceFiles(): array
{
    $files = [];
    $directory = new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src');

    foreach (new RecursiveIteratorIterator($directory) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('keeps Laravel out of everything but the Laravel layer', function () {
    $root = dirname(__DIR__, 2).'/src/';

    foreach (sourceFiles() as $file) {
        $relative = str_replace($root, '', $file);

        if (in_array($relative, LARAVEL_LAYER, true)) {
            continue;
        }

        expect(file_get_contents($file))
            ->not->toMatch('/^use\s+Illuminate\\\\/m', $relative.' may not depend on Illuminate')
            ->not->toMatch('/\bIlluminate\\\\/', $relative.' may not depend on Illuminate');
    }
});

it('lists only files that exist, so the exception list cannot go stale', function () {
    foreach (LARAVEL_LAYER as $relative) {
        expect(dirname(__DIR__, 2).'/src/'.$relative)->toBeFile();
    }
});

it('builds an invoice with nothing but the DOM extension loaded', function () {
    expect(extension_loaded('dom'))->toBeTrue();

    $service = new Darvis\UblPeppol\UblNlBis3Service();
    $service->createDocument();

    expect($service->generateXml())->toContain('<Invoice');
});
