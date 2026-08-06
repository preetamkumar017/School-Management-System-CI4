<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Core\Http\RequestContext;
use App\Modules\Administration\DTOs\ChangePasswordRequest;
use App\Modules\Administration\DTOs\LoginRequest;
use App\Modules\Administration\DTOs\RefreshRequest;
use Config\Services;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/auth — the one Controller reachable without a valid
 * access token (login, refresh); logout/logout-all/change-password still
 * require one, enforced by the jwtauth filter on their routes.
 */
class AuthController extends BaseController
{
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

    public function refresh()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new RefreshRequest(refreshToken: (string) ($body['refresh_token'] ?? ''));

        $result = Services::authService()->refresh($request);

        return $this->respondSuccess($result);
    }

    public function logout()
    {
        $body = $this->request->getJSON(true) ?? [];

        Services::authService()->logout(
            (int) RequestContext::userId(),
            (string) ($body['refresh_token'] ?? ''),
        );

        return $this->respondSuccess();
    }

    public function logoutAll()
    {
        Services::authService()->logoutAll((int) RequestContext::userId());

        return $this->respondSuccess();
    }

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
