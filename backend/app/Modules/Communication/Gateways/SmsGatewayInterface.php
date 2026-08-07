<?php

declare(strict_types=1);

namespace App\Modules\Communication\Gateways;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md §a — the
 * pluggable seam. A future non-MSG91 SMS vendor is a new class
 * implementing this interface, not a rewrite of NotificationLogService.
 */
interface SmsGatewayInterface
{
    /**
     * @param string $mobileNumber a 10-digit Indian mobile number, no
     *                             country-code prefix (the driver adds it)
     *
     * @throws GatewayException on any non-2xx response or network failure
     */
    public function send(string $mobileNumber, string $message): void;
}
