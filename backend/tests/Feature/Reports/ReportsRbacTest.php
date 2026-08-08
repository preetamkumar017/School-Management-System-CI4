<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Reports\Services\ReportsService;
use Config\Services;
use Tests\Support\Communication\CommunicationTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — Reports is
 * Tier-1-only (`reports.manage`). All public methods (including reads) are
 * gated by `reports.manage` because aggregate reports span school-wide data.
 */
final class ReportsRbacTest extends CommunicationTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testGetSummaryRejectedWithoutManagePermission(): void
    {
        $user = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::reportsService()->getSummary(),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testGetSummarySucceedsWithManagePermission(): void
    {
        $user = $this->createUser($this->createRole([ReportsService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([ReportsService::PERMISSION_MANAGE]);

        $response = Services::reportsService()->getSummary();

        $this->assertNotNull($response->generatedAt);
    }

    public function testGetFeeCollectionSummaryRejectedWithoutManagePermission(): void
    {
        $sessionId = $this->createAcademicSession();
        $user      = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::reportsService()->getFeeCollectionSummary($sessionId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
