<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

final class MarkNotificationFailedRequest
{
    public function __construct(
        public readonly string $failureReason,
    ) {
    }
}
