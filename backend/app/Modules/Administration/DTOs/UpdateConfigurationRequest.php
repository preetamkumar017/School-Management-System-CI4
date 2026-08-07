<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class UpdateConfigurationRequest
{
    public function __construct(
        public readonly string $settingValue,
    ) {
    }
}
