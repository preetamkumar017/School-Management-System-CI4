<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * @internal
 */
final class UserTest extends AdministrationTestCase
{
    public function testCreateUserNeverReturnsPasswordHash(): void
    {
        $admin  = $this->createUser();
        $tokens = $this->loginAs($admin['username']);
        $roleId = $this->createRole();

        $response = $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->withBodyFormat('json')
            ->post('api/v1/administration/users', [
                'username'     => 'new_user_' . uniqid('', true),
                'password'     => 'Whatever@123',
                'role_id'      => $roleId,
                'owner_type'   => 'STUDENT',
                'owner_ref_id' => 42,
            ]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('password_hash', $this->decode($response)['data']);
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $admin   = $this->createUser();
        $tokens  = $this->loginAs($admin['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $roleId  = $this->createRole();

        $username = 'taken_' . uniqid('', true);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/users', [
            'username' => $username, 'password' => 'Whatever@123', 'role_id' => $roleId,
            'owner_type' => 'STUDENT', 'owner_ref_id' => 1,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/users', [
                'username' => $username, 'password' => 'Whatever@123', 'role_id' => $roleId,
                'owner_type' => 'STUDENT', 'owner_ref_id' => 2,
            ]),
            BusinessRuleException::class,
            'USERNAME_ALREADY_TAKEN',
            422,
        );
    }

    public function testDeactivatingAUserRevokesTheirSessions(): void
    {
        $admin       = $this->createUser();
        $adminTokens = $this->loginAs($admin['username']);
        $headers     = $this->authHeaders($adminTokens['access_token']);

        $target       = $this->createUser();
        $targetTokens = $this->loginAs($target['username']);

        $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/administration/users/{$target['id']}/status", ['status' => 'DEACTIVATED'])
            ->assertStatus(200);

        $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/refresh', [
                'refresh_token' => $targetTokens['refresh_token'],
            ]),
            AuthorizationException::class,
            'REFRESH_TOKEN_INVALID',
            401,
        );
    }
}
