<?php

declare(strict_types=1);

namespace App\Filters;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Validates the Bearer access token on every route it's applied to and
 * populates RequestContext with the caller's identity/role/permissions.
 * Company Development Standard §9 — RBAC reads the permission set from
 * here, never by re-querying Role per request.
 */
class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! str_starts_with($header, 'Bearer ')) {
            throw new AuthorizationException('MISSING_ACCESS_TOKEN', 'An access token is required.', 401);
        }

        $token = substr($header, 7);
        $claims = Services::jwtManager()->decodeAccessToken($token);

        RequestContext::setUserId((int) $claims->user_id);
        RequestContext::setRoleId((int) $claims->role_id);
        RequestContext::setPermissionSet((array) $claims->permission_set);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
