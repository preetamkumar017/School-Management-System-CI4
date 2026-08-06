<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\AuthorizationException;
use App\Modules\Administration\Models\UserModel;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * @internal
 */
final class AuthTest extends AdministrationTestCase
{
    public function testLoginWithCorrectCredentialsIssuesTokens(): void
    {
        $user = $this->createUser();

        $response = $this->withBodyFormat('json')->post('api/v1/auth/login', [
            'username' => $user['username'],
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertStatus(200);
        $body = $this->decode($response);

        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']['access_token']);
        $this->assertNotEmpty($body['data']['refresh_token']);
    }

    public function testWrongPasswordAndNonexistentUsernameProduceIdenticalResponses(): void
    {
        $user = $this->createUser();

        $wrongPasswordException = $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/login', [
                'username' => $user['username'],
                'password' => 'not-the-password',
            ]),
            AuthorizationException::class,
            'INVALID_CREDENTIALS',
            401,
        );

        $nonexistentUserException = $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/login', [
                'username' => 'no-such-user-' . uniqid('', true),
                'password' => 'not-the-password',
            ]),
            AuthorizationException::class,
            'INVALID_CREDENTIALS',
            401,
        );

        // Anti-enumeration: identical exception for both cases.
        $this->assertSame($wrongPasswordException->getMessage(), $nonexistentUserException->getMessage());
    }

    public function testFifthFailedAttemptLocksTheAccount(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->withBodyFormat('json')->post('api/v1/auth/login', [
                    'username' => $user['username'],
                    'password' => 'wrong',
                ]);
            } catch (AuthorizationException) {
                // expected on every attempt
            }
        }

        // Correct password no longer works once locked.
        $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/login', [
                'username' => $user['username'],
                'password' => self::TEST_PASSWORD,
            ]),
            AuthorizationException::class,
            'ACCOUNT_LOCKED',
            403,
        );

        $stored = (new UserModel())->find($user['id']);
        $this->assertSame('LOCKED', $stored->status);
    }

    public function testRefreshIssuesNewAccessTokenWithoutRotatingRefreshToken(): void
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        $response = $this->withBodyFormat('json')->post('api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($this->decode($response)['data']['access_token']);
    }

    public function testRevokedRefreshTokenIsRejected(): void
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->withBodyFormat('json')
            ->post('api/v1/auth/logout', ['refresh_token' => $tokens['refresh_token']]);

        $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/refresh', [
                'refresh_token' => $tokens['refresh_token'],
            ]),
            AuthorizationException::class,
            'REFRESH_TOKEN_INVALID',
            401,
        );
    }

    public function testChangePasswordRevokesAllSessions(): void
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->withBodyFormat('json')
            ->post('api/v1/auth/change-password', [
                'current_password' => self::TEST_PASSWORD,
                'new_password'     => 'NewPass@5678',
            ])
            ->assertStatus(200);

        // The refresh token issued before the password change is now dead.
        $this->assertApiException(
            fn () => $this->withBodyFormat('json')->post('api/v1/auth/refresh', [
                'refresh_token' => $tokens['refresh_token'],
            ]),
            AuthorizationException::class,
            'REFRESH_TOKEN_INVALID',
            401,
        );
    }

    public function testProtectedRouteRejectsMissingToken(): void
    {
        $this->assertApiException(
            fn () => $this->get('api/v1/administration/users'),
            AuthorizationException::class,
            'MISSING_ACCESS_TOKEN',
            401,
        );
    }

    public function testProtectedRouteAcceptsValidToken(): void
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->get('api/v1/administration/users')
            ->assertStatus(200);
    }
}
