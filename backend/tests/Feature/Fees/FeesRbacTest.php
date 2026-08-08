<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Fees\Services\InvoiceService;
use Config\Services;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — `fees.manage`
 * (Tier 1) gates generation/late-fee/defaulter-flagging; `getInvoice()`
 * allows Tier 2 — a Student may read their own Invoice. Void/refund keeps
 * ADR-018's separate `fees.payment.void_refund` check, not touched here.
 */
final class FeesRbacTest extends FeesTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testGetInvoiceSucceedsForCallerWithManagePermission(): void
    {
        $invoiceId = $this->createInvoiceFixture();
        $user      = $this->createUser($this->createRole([InvoiceService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([InvoiceService::PERMISSION_MANAGE]);

        $response = Services::invoiceService()->getInvoice($invoiceId);

        $this->assertSame($invoiceId, $response->invoiceId);
    }

    public function testGetInvoiceSucceedsForOwningStudent(): void
    {
        $studentId = $this->createStudentFixture();
        $invoiceId = $this->createInvoiceFixture($studentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'fee_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::invoiceService()->getInvoice($invoiceId);

        $this->assertSame($invoiceId, $response->invoiceId);
    }

    public function testGetInvoiceRejectedForDifferentStudentOwner(): void
    {
        $ownerStudentId = $this->createStudentFixture();
        $otherStudentId = $this->createStudentFixture();
        $invoiceId      = $this->createInvoiceFixture($ownerStudentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'fee_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::invoiceService()->getInvoice($invoiceId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testApplyLateFeeRejectedForCallerWithoutManagePermission(): void
    {
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0, 'UNPAID', '2020-01-01');
        $user      = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::invoiceService()->applyLateFee($invoiceId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
