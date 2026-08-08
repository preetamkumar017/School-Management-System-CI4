<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Library\Services\BookIssueService;
use App\Modules\Library\Services\BookService;
use Config\Services;
use Tests\Support\Library\LibraryTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 —
 * `library.manage` (Tier 1) gates Book CRUD and BookIssue writes;
 * `getBookIssue()`/`listByBorrower()` allow Tier 2 directly via
 * `borrower_type`/`borrower_ref_id` — the simplest Tier 2 case in this
 * pass.
 */
final class LibraryRbacTest extends LibraryTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testCreateBookRejectedForCallerWithoutManagePermission(): void
    {
        $user = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::bookService()->createBook(new \App\Modules\Library\DTOs\CreateBookRequest(
                'BC-' . random_int(100000, 999999),
                'Test Title',
                'Test Author',
                'Circulating',
            )),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testCreateBookSucceedsForCallerWithManagePermission(): void
    {
        $user = $this->createUser($this->createRole([BookService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([BookService::PERMISSION_MANAGE]);

        $response = Services::bookService()->createBook(new \App\Modules\Library\DTOs\CreateBookRequest(
            'BC-' . random_int(100000, 999999),
            'Test Title',
            'Test Author',
            'Circulating',
        ));

        $this->assertNotNull($response->bookId);
    }

    public function testGetBookIssueSucceedsForOwningBorrower(): void
    {
        $studentId    = $this->createStudentFixture();
        $bookIssueId  = $this->createBookIssueFixture(null, 'Student', $studentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'lib_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::bookIssueService()->getBookIssue($bookIssueId);

        $this->assertSame($bookIssueId, $response->bookIssueId);
    }

    public function testGetBookIssueRejectedForDifferentBorrower(): void
    {
        $ownerStudentId = $this->createStudentFixture();
        $otherStudentId = $this->createStudentFixture();
        $bookIssueId    = $this->createBookIssueFixture(null, 'Student', $ownerStudentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'lib_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::bookIssueService()->getBookIssue($bookIssueId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
