<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Admission\DTOs\ApplicationVerifyRequest;
use App\Modules\Admission\Services\ApplicationService;
use Config\Services;
use Tests\Support\Admission\AdmissionTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — Admission is
 * Tier-1-only (`admission.manage`); Application has no login-carrying
 * owner (an applicant never has a User account in this system).
 */
final class AdmissionRbacTest extends AdmissionTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testVerifyApplicationSucceedsForCallerWithManagePermission(): void
    {
        $applicationId = $this->createApplicationFixture();
        $user          = $this->createUser($this->createRole([ApplicationService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([ApplicationService::PERMISSION_MANAGE]);

        $response = Services::applicationService()->verifyApplication($applicationId, new ApplicationVerifyRequest());

        $this->assertSame('VERIFIED', $response->status);
    }

    public function testVerifyApplicationRejectedForCallerWithoutManagePermission(): void
    {
        $applicationId = $this->createApplicationFixture();
        $user          = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::applicationService()->verifyApplication($applicationId, new ApplicationVerifyRequest()),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
