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

    protected function createRole(array $permissionSet = ['read', 'create', 'update', 'delete']): int
    {
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
