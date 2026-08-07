<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

final class CreateNotificationLogRequest
{
    public function __construct(
        public readonly string $recipientType,
        public readonly int $recipientRefId,
        public readonly string $channel,
        public readonly string $triggerEvent,
        public readonly ?string $messageBody = null,
    ) {
    }
}
