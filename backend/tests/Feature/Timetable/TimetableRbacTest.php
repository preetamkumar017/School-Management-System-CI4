<?php

declare(strict_types=1);

namespace Tests\Feature\Timetable;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Timetable\Services\TimetableEntryService;
use Config\Services;
use Tests\Support\Timetable\TimetableTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — Timetable is
 * Tier-1-only (`timetable.manage`), no owned entities.
 */
final class TimetableRbacTest extends TimetableTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testPublishEntrySucceedsForCallerWithManagePermission(): void
    {
        $entryId = $this->createTimetableEntryFixture(null, null, null, 'MONDAY', null, 'DRAFT');
        $user    = $this->createUser($this->createRole([TimetableEntryService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([TimetableEntryService::PERMISSION_MANAGE]);

        $response = Services::timetableEntryService()->publishEntry($entryId);

        $this->assertSame('PUBLISHED', $response->status);
    }

    public function testPublishEntryRejectedForCallerWithoutManagePermission(): void
    {
        $entryId = $this->createTimetableEntryFixture(null, null, null, 'MONDAY', null, 'DRAFT');
        $user    = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::timetableEntryService()->publishEntry($entryId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
