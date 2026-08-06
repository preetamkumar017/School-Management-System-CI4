<?php

declare(strict_types=1);

namespace App\Core\Http;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Per-request state that needs to be readable from places that don't
 * receive the request directly (BaseModel's audit-column population,
 * the exception handler, structured logging). Set once, early, by
 * RequestContextFilter; nothing else should construct these values.
 */
class RequestContext
{
    private static ?string $requestId = null;
    private static ?int $userId       = null;
    private static ?int $roleId       = null;

    /** @var list<string> */
    private static array $permissionSet = [];

    public static function resolveRequestId(RequestInterface $request): string
    {
        $header = $request->getHeaderLine('X-Request-Id');

        return $header !== '' ? $header : self::generateUuidV4();
    }

    public static function setRequestId(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    public static function setUserId(?int $userId): void
    {
        self::$userId = $userId;
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }

    public static function setRoleId(?int $roleId): void
    {
        self::$roleId = $roleId;
    }

    public static function roleId(): ?int
    {
        return self::$roleId;
    }

    /**
     * @param list<string> $permissionSet
     */
    public static function setPermissionSet(array $permissionSet): void
    {
        self::$permissionSet = $permissionSet;
    }

    /**
     * @return list<string>
     */
    public static function permissionSet(): array
    {
        return self::$permissionSet;
    }

    /**
     * Reset between requests in long-running contexts (CLI test runners,
     * queue workers) so state never leaks across requests.
     */
    public static function reset(): void
    {
        self::$requestId    = null;
        self::$userId       = null;
        self::$roleId       = null;
        self::$permissionSet = [];
    }

    private static function generateUuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
