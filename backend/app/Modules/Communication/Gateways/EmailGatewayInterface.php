<?php

declare(strict_types=1);

namespace App\Modules\Communication\Gateways;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md §a — the
 * pluggable seam. A future non-MSG91 Email vendor is a new class
 * implementing this interface, not a rewrite of NotificationLogService.
 */
interface EmailGatewayInterface
{
    /**
     * @throws GatewayException on any non-2xx response or network failure
     */
    public function sendEmail(string $emailAddress, string $subject, string $message): void;
}
