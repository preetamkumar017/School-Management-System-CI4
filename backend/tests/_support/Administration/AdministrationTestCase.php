<?php

declare(strict_types=1);

namespace Tests\Support\Administration;

use App\Core\Exceptions\ApiException;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * @internal
 */
abstract class AdministrationTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    // CIUnitTestCase defaults this to 'Tests\Support', which would only
    // discover tests/_support/Database/Migrations — null runs every
    // namespace's migrations, including App\Database\Migrations where the
    // real schema lives.
    protected $namespace = null;

    protected const TEST_PASSWORD = 'Test@1234';

    /**
     * ADR-024 §3: every module's Service layer now enforces a
     * `<module>.manage` permission (Tier 1) at its mutation/read entry
     * points. Prior to ADR-024, `createRole()`'s default
     * (`['read','create','update','delete']`) was effectively
     * full-access — nothing ever read `permission_set` outside
     * ADR-015/018's two narrow checks — so every test written against
     * `createUser()`'s default role implicitly assumed full access. This
     * keeps that assumption true post-ADR-024 by granting every
     * `<module>.manage` string by default; tests that specifically need
     * to prove a *rejection* (no manage permission, no ownership) create
     * their own narrower role instead, same as
     * `LeaveRequestService::PERMISSION_OVERRIDE`'s existing precedent.
     *
     * @var list<string>
     */
    protected const ALL_MODULE_MANAGE_PERMISSIONS = [
        'academic.manage',
        'admission.manage',
        'sis.manage',
        'examination.manage',
        'timetable.manage',
        'attendance.manage',
        'fees.manage',
        'hr_payroll.manage',
        'library.manage',
        'transport.manage',
        'communication.manage',
        'administration.manage',
        'reports.manage',
    ];

    protected function createRole(?array $permissionSet = null): int
    {
        $permissionSet ??= array_merge(['read', 'create', 'update', 'delete'], self::ALL_MODULE_MANAGE_PERMISSIONS);

        return (new RoleModel())->insert([
            'role_name'      => 'Role ' . uniqid('', true),
            'is_system_role' => false,
            'permission_set' => $permissionSet,
        ], true);
    }

    protected function createUser(?int $roleId = null, string $status = 'ACTIVE'): array
    {
        $roleId ??= $this->createRole();

        $username = 'user_' . uniqid('', true);

        $userId = (new UserModel())->insert([
            'username'      => $username,
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => 1,
            'status'        => $status,
        ], true);

        return ['id' => $userId, 'username' => $username, 'role_id' => $roleId];
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    protected function loginAs(string $username): array
    {
        $response = $this->withBodyFormat('json')->post('api/v1/auth/login', [
            'username' => $username,
            'password' => self::TEST_PASSWORD,
        ]);

        return $this->decode($response)['data'];
    }

    protected function authHeaders(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
    }

    /**
     * TestResponse::getJSON() returns the raw JSON string, not a decoded
     * array (unlike, e.g., Laravel's equivalent) — every test decodes
     * through this rather than repeating json_decode(..., true) everywhere.
     *
     * @return array<string, mixed>
     */
    protected function decode(TestResponse $response): array
    {
        return json_decode($response->getJSON(), true);
    }

    /**
     * FeatureTestTrait::call() bypasses Boot::bootWeb()'s top-level
     * set_exception_handler() registration, so an ApiException thrown
     * during a simulated request propagates raw to PHPUnit instead of
     * being rendered by ApiExceptionHandler into a JSON response, the way
     * it verifiably does on a real request (checked by hand against the
     * dev server for every error path this covers). This asserts on the
     * same category/code/httpStatus ApiExceptionHandler would have used to
     * build that response, without needing the full boot sequence.
     */
    protected function assertApiException(
        callable $action,
        string $expectedClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
    ): ApiException {
        try {
            $action();
        } catch (ApiException $e) {
            $this->assertInstanceOf($expectedClass, $e);
            $this->assertSame($expectedErrorCode, $e->errorCode());
            $this->assertSame($expectedHttpStatus, $e->httpStatus());

            return $e;
        }

        $this->fail("Expected {$expectedClass} ({$expectedErrorCode}) was not thrown.");
    }
}
