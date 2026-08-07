<?php

declare(strict_types=1);

namespace Tests\Support\Communication;

use App\Modules\Communication\Gateways\EmailGatewayInterface;
use App\Modules\Communication\Gateways\GatewayException;

/**
 * Deterministic, offline test double for EmailGatewayInterface — records
 * every sendEmail() call and either succeeds or throws, per
 * $failureMessage, so Feature tests never call MSG91's real API.
 */
final class FakeEmailGateway implements EmailGatewayInterface
{
    /** @var list<array{email: string, subject: string, message: string}> */
    public array $calls = [];

    public function __construct(private readonly ?string $failureMessage = null)
    {
    }

    public function sendEmail(string $emailAddress, string $subject, string $message): void
    {
        $this->calls[] = ['email' => $emailAddress, 'subject' => $subject, 'message' => $message];

        if ($this->failureMessage !== null) {
            throw new GatewayException($this->failureMessage);
        }
    }
}
