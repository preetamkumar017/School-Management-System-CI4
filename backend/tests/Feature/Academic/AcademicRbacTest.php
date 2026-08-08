<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Academic\DTOs\CreateClassRequest;
use App\Modules\Academic\Services\ClassService;
use Config\Services;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — Academic is
 * Tier-1-only (`academic.manage`), no owned entities.
 */
final class AcademicRbacTest extends AcademicTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testCreateClassSucceedsForCallerWithManagePermission(): void
    {
        $user = $this->createUser($this->createRole([ClassService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([ClassService::PERMISSION_MANAGE]);

        $response = Services::classService()->createClass(new CreateClassRequest('C' . uniqid('', false), random_int(1000, 999999)));

        $this->assertNotNull($response->classId);
    }

    public function testCreateClassRejectedForCallerWithoutManagePermission(): void
    {
        $user = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::classService()->createClass(new CreateClassRequest('C' . uniqid('', false), random_int(1000, 999999))),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
