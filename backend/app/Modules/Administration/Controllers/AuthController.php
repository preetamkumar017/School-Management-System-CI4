<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Core\Http\RequestContext;
use App\Modules\Administration\DTOs\ChangePasswordRequest;
use App\Modules\Administration\DTOs\LoginRequest;
use App\Modules\Administration\DTOs\RefreshRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/auth — the one Controller reachable without a valid
 * access token (login, refresh); logout/logout-all/change-password still
 * require one, enforced by the jwtauth filter on their routes.
 */
#[OA\Tag(name: 'Auth')]
class AuthController extends BaseController
{
    #[OA\Post(
        path: '/auth/login',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access + refresh token pair issued.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                        new OA\Property(property: 'access_token_expires_at', type: 'string', format: 'date-time'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials (identical response whether the username exists or not — anti-enumeration), or account locked/deactivated.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function login()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new LoginRequest(
            username: (string) ($body['username'] ?? ''),
            password: (string) ($body['password'] ?? ''),
        );

        $result = Services::authService()->login($request);

        return $this->respondSuccess($result);
    }

    #[OA\Post(
        path: '/auth/refresh',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [new OA\Property(property: 'refresh_token', type: 'string')],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'New access token. The refresh token is not rotated — it keeps its original expiry.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'access_token_expires_at', type: 'string', format: 'date-time'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(
                response: 401,
                description: 'Refresh token is invalid, expired, or revoked — caller must log in again.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function refresh()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new RefreshRequest(refreshToken: (string) ($body['refresh_token'] ?? ''));

        $result = Services::authService()->refresh($request);

        return $this->respondSuccess($result);
    }

    #[OA\Post(
        path: '/auth/logout',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [new OA\Property(property: 'refresh_token', type: 'string', description: 'Revokes this one session only.')],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Session revoked.')],
    )]
    public function logout()
    {
        $body = $this->request->getJSON(true) ?? [];

        Services::authService()->logout(
            (int) RequestContext::userId(),
            (string) ($body['refresh_token'] ?? ''),
        );

        return $this->respondSuccess();
    }

    #[OA\Post(
        path: '/auth/logout-all',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Every session for this user revoked.')],
    )]
    public function logoutAll()
    {
        Services::authService()->logoutAll((int) RequestContext::userId());

        return $this->respondSuccess();
    }

    #[OA\Post(
        path: '/auth/change-password',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed; every session for this user is revoked, including the one making this call.'),
            new OA\Response(response: 401, description: 'Current password incorrect.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function changePassword()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new ChangePasswordRequest(
            currentPassword: (string) ($body['current_password'] ?? ''),
            newPassword: (string) ($body['new_password'] ?? ''),
        );

        Services::authService()->changePassword((int) RequestContext::userId(), $request);

        return $this->respondSuccess();
    }
}
