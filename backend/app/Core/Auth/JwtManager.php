<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Exceptions\AuthorizationException;
use Config\Auth as AuthConfig;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use UnexpectedValueException;

/**
 * Pure JWT issuance/validation — no database access, no knowledge of the
 * `User` entity. AuthService (Administration module) is the only caller;
 * it supplies the claims and interprets the decoded payload.
 *
 * Payload carries identity/role/authority claims only, never PII
 * (Company Development Standard §9): user_id, role_id, permission_set.
 */
class JwtManager
{
    private const ALGO = 'HS256';

    public function __construct(private readonly AuthConfig $config)
    {
    }

    /**
     * @param list<string> $permissionSet
     */
    public function issueAccessToken(int $userId, int $roleId, array $permissionSet): string
    {
        $now = time();

        return JWT::encode([
            'user_id'        => $userId,
            'role_id'        => $roleId,
            'permission_set' => $permissionSet,
            'iat'            => $now,
            'exp'            => $now + $this->config->accessTokenTTL,
        ], $this->config->jwtSecret, self::ALGO);
    }

    public function accessTokenTtl(): int
    {
        return $this->config->accessTokenTTL;
    }

    /**
     * @throws AuthorizationException on any invalid/expired token
     */
    public function decodeAccessToken(string $token): stdClass
    {
        try {
            return JWT::decode($token, new Key($this->config->jwtSecret, self::ALGO));
        } catch (ExpiredException) {
            throw new AuthorizationException('ACCESS_TOKEN_EXPIRED', 'Access token has expired.', 401);
        } catch (SignatureInvalidException | UnexpectedValueException) {
            throw new AuthorizationException('ACCESS_TOKEN_INVALID', 'Access token is invalid.', 401);
        }
    }

    /**
     * The plain value is returned to the caller once and never stored;
     * only its hash is persisted (RefreshTokenModel).
     */
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashRefreshToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function refreshTokenTtlSeconds(): int
    {
        return $this->config->refreshTokenTTL;
    }
}
