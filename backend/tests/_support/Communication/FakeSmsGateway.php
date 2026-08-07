<?php

declare(strict_types=1);

namespace Tests\Support\Communication;

use App\Modules\Communication\Gateways\GatewayException;
use App\Modules\Communication\Gateways\SmsGatewayInterface;

/**
 * Deterministic, offline test double for SmsGatewayInterface — records
 * every send() call and either succeeds or throws, per
 * $failureMessage, so Feature tests never call MSG91's real API.
 */
final class FakeSmsGateway implements SmsGatewayInterface
{
    /** @var list<array{mobile: string, message: string}> */
    public array $calls = [];

    public function __construct(private readonly ?string $failureMessage = null)
    {
    }

    public function send(string $mobileNumber, string $message): void
    {
        $this->calls[] = ['mobile' => $mobileNumber, 'message' => $message];

        if ($this->failureMessage !== null) {
            throw new GatewayException($this->failureMessage);
        }
    }
}
