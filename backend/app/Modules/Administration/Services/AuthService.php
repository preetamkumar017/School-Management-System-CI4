<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Auth\JwtManager;
use App\Core\Exceptions\AuthorizationException;
use App\Modules\Administration\DTOs\ChangePasswordRequest;
use App\Modules\Administration\DTOs\LoginRequest;
use App\Modules\Administration\DTOs\RefreshRequest;
use App\Modules\Administration\Entities\Role;
use App\Modules\Administration\Entities\User;
use App\Modules\Administration\Models\RefreshTokenModel;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use CodeIgniter\I18n\Time;

/**
 * docs/design/administration/Phase-4-Service-Design.md
 *
 * Lockout, anti-enumeration, and JWT rules per Company Development
 * Standard §9. JWT payload carries user_id/role_id/permission_set only —
 * never PII.
 */
class AuthService
{
    private const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    public function __construct(
        private readonly UserModel $userModel,
        private readonly RoleModel $roleModel,
        private readonly RefreshTokenModel $refreshTokenModel,
        private readonly JwtManager $jwt,
    ) {
    }

    /**
     * @return array{access_token: string, refresh_token: string, access_token_expires_at: string}
     */
    public function login(LoginRequest $request): array
    {
        $user = $this->userModel->findByUsername($request->username);

        // Anti-enumeration: a nonexistent username and a wrong password
        // produce the identical response — see docs/design/administration/Phase-4.
        if ($user === null || ! password_verify($request->password, $user->password_hash)) {
            if ($user !== null) {
                $this->registerFailedAttempt($user);
            }

            throw new AuthorizationException('INVALID_CREDENTIALS', 'Invalid username or password.', 401);
        }

        if ($user->status === User::STATUS_LOCKED) {
            throw new AuthorizationException(
                'ACCOUNT_LOCKED',
                'This account is locked. Contact an administrator.',
                403,
            );
        }

        if ($user->status === User::STATUS_DEACTIVATED) {
            throw new AuthorizationException('ACCOUNT_DEACTIVATED', 'This account is deactivated.', 403);
        }

        $this->userModel->resetFailedLoginCount($user->user_id);
        $this->userModel->update($user->user_id, ['last_login_at' => Time::now()->toDateTimeString()]);

        return $this->issueTokenPair($user);
    }

    /**
     * @return array{access_token: string, access_token_expires_at: string}
     */
    public function refresh(RefreshRequest $request): array
    {
        $tokenHash = $this->jwt->hashRefreshToken($request->refreshToken);
        $stored    = $this->refreshTokenModel->findValidByTokenHash($tokenHash);

        if ($stored === null) {
            throw new AuthorizationException(
                'REFRESH_TOKEN_INVALID',
                'Refresh token is invalid, expired, or revoked. Please log in again.',
                401,
            );
        }

        $user = $this->userModel->find($stored->user_id);

        if ($user === null || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('REFRESH_TOKEN_INVALID', 'Please log in again.', 401);
        }

        $role = $this->roleModel->where('role_id', $user->role_id)->first();
        $permissionSet = $role instanceof Role ? $role->permission_set : [];
        $accessToken = $this->jwt->issueAccessToken($user->user_id, $user->role_id, $permissionSet);

        return [
            'access_token'            => $accessToken,
            'access_token_expires_at' => Time::now()->addSeconds($this->jwt->accessTokenTtl())->toDateTimeString(),
        ];
    }

    public function logout(int $userId, string $refreshToken): void
    {
        $this->refreshTokenModel->revokeByTokenHash($this->jwt->hashRefreshToken($refreshToken));
    }

    public function logoutAll(int $userId): void
    {
        $this->refreshTokenModel->revokeAllForUser($userId);
    }

    public function changePassword(int $userId, ChangePasswordRequest $request): void
    {
        $user = $this->userModel->find($userId);

        if ($user === null || ! password_verify($request->currentPassword, $user->password_hash)) {
            throw new AuthorizationException('CURRENT_PASSWORD_INCORRECT', 'Current password is incorrect.', 401);
        }

        $this->userModel->update($userId, [
            'password_hash' => password_hash($request->newPassword, PASSWORD_BCRYPT),
        ]);

        // "Password change revokes all sessions" is not optional or
        // caller-controlled (Company Development Standard §9).
        $this->logoutAll($userId);
    }

    private function registerFailedAttempt(User $user): void
    {
        $count = $this->userModel->incrementFailedLoginCount($user->user_id);

        if ($count >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $this->userModel->update($user->user_id, ['status' => User::STATUS_LOCKED]);
        }
    }

    /**
     * @return array{access_token: string, refresh_token: string, access_token_expires_at: string}
     */
    private function issueTokenPair(User $user): array
    {
        $role = $this->roleModel->where('role_id', $user->role_id)->first();
        $permissionSet = $role instanceof Role ? $role->permission_set : [];

        $accessToken  = $this->jwt->issueAccessToken($user->user_id, $user->role_id, $permissionSet);
        $refreshPlain = $this->jwt->generateRefreshToken();

        $this->refreshTokenModel->insert([
            'user_id'    => $user->user_id,
            'token_hash' => $this->jwt->hashRefreshToken($refreshPlain),
            'expires_at' => Time::now()->addSeconds($this->jwt->refreshTokenTtlSeconds())->toDateTimeString(),
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        return [
            'access_token'            => $accessToken,
            'refresh_token'           => $refreshPlain,
            'access_token_expires_at' => Time::now()->addSeconds($this->jwt->accessTokenTtl())->toDateTimeString(),
        ];
    }
}
