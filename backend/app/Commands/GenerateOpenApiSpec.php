<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use OpenApi\Generator;

/**
 * `php spark openapi:generate` — scans every OpenAPI attribute under
 * app/Core and app/Modules and writes the merged spec to
 * public/openapi.json. This is the only way the spec file changes; it is
 * never hand-edited (Company Development Standard §5 — API docs are a
 * generated build artifact, not hand-maintained).
 */
class GenerateOpenApiSpec extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'openapi:generate';
    protected $description = 'Generates public/openapi.json from OpenAPI attributes on Controllers and App\Core\OpenApi\Spec.';
    protected $usage       = 'openapi:generate';

    public function run(array $params)
    {
        $openapi = (new Generator())->generate([APPPATH . 'Core', APPPATH . 'Modules']);

        if ($openapi === null) {
            CLI::error('OpenAPI generation failed — no annotated routes found.');

            return;
        }

        $outputPath = FCPATH . 'openapi.json';
        file_put_contents($outputPath, $openapi->toJson());

        CLI::write('OpenAPI spec written to ' . $outputPath, 'green');
    }
}
