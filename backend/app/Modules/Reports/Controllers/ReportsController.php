<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\BaseController;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/reports/Phase-1-Service-Controller-Design.md
 * Base path /api/v1/reports/summary
 */
#[OA\Tag(name: 'Reports')]
class ReportsController extends BaseController
{
    #[OA\Get(
        path: '/reports/summary',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SummaryResponse'))],
    )]
    public function summary()
    {
        return $this->respondSuccess(Services::reportsService()->getSummary()->toArray());
    }
}
