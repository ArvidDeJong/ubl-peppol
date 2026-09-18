<?php

declare(strict_types=1);

use Darvis\UblPeppol\Tests\TestCase;

// Only the Laravel layer boots an application; the rest of the suite is plain PHP.
uses(TestCase::class)->in('Laravel');
