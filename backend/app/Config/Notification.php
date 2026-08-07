<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md §b — MSG91
 * gateway settings, sourced from .env exactly like Config\Auth's
 * jwtSecret. Never hardcode a real authkey here.
 */
class Notification extends BaseConfig
{
    public string $msg91AuthKey;

    public string $msg91SenderId;

    public string $msg91SmsFlowUrl;

    public string $msg91EmailUrl;

    public string $msg91FromEmail;

    public string $msg91FromEmailDomain;

    public function __construct()
    {
        parent::__construct();

        $this->msg91AuthKey         = (string) env('notification.msg91.authKey', '');
        $this->msg91SenderId        = (string) env('notification.msg91.senderId', 'SCHOOL');
        $this->msg91SmsFlowUrl      = (string) env('notification.msg91.smsFlowUrl', 'https://control.msg91.com/api/v5/flow/');
        $this->msg91EmailUrl        = (string) env('notification.msg91.emailUrl', 'https://api.msg91.com/api/v5/email/send');
        $this->msg91FromEmail       = (string) env('notification.msg91.fromEmail', 'no-reply@school.example');
        $this->msg91FromEmailDomain = (string) env('notification.msg91.fromEmailDomain', '');
    }
}
