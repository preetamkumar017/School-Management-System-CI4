<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\Configuration;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md
 */
final class ConfigurationResponse
{
    public readonly int $settingId;
    public readonly string $settingKey;
    public readonly string $settingValue;
    public readonly string $dataType;
    public readonly string $module;
    public readonly bool $isEditable;

    public function __construct(Configuration $configuration)
    {
        $this->settingId    = $configuration->setting_id;
        $this->settingKey   = $configuration->setting_key;
        $this->settingValue = $configuration->setting_value;
        $this->dataType     = $configuration->data_type;
        $this->module       = $configuration->module;
        $this->isEditable   = $configuration->is_editable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'setting_id'    => $this->settingId,
            'setting_key'   => $this->settingKey,
            'setting_value' => $this->settingValue,
            'data_type'     => $this->dataType,
            'module'        => $this->module,
            'is_editable'   => $this->isEditable,
        ];
    }
}
