<?php

declare(strict_types=1);

namespace Tests\Support\Reports;

use CodeIgniter\Test\TestResponse;
use ReflectionProperty;

/**
 * @internal
 * DownloadResponse (CodeIgniter\HTTP\DownloadResponse) never populates the
 * normal response body — CodeIgniter\CodeIgniter::gatherOutput() special-
 * cases it and returns early — so the only way to inspect the actual
 * streamed bytes in a feature test is via its private $binary property.
 */
trait ReportsExportAssertions
{
    protected function extractDownloadBinary(TestResponse $response): string
    {
        $downloadResponse = $response->response();
        $property          = new ReflectionProperty($downloadResponse, 'binary');
        $property->setAccessible(true);

        return (string) $property->getValue($downloadResponse);
    }
}
