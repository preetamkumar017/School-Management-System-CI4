<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * JWT settings (Company Development Standard §9). Values come from .env —
 * never hardcode a real secret here.
 */
class Auth extends BaseConfig
{
    public string $jwtSecret;

    public int $accessTokenTTL;

    public int $refreshTokenTTL;

    public function __construct()
    {
        parent::__construct();

        $this->jwtSecret       = (string) env('auth.jwt.secret', '');
        $this->accessTokenTTL  = (int) env('auth.jwt.accessTokenTTL', 900);
        $this->refreshTokenTTL = (int) env('auth.jwt.refreshTokenTTL', 604800);
    }
}
