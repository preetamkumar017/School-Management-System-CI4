<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\ConfigurationResponse;
use App\Modules\Administration\DTOs\UpdateConfigurationRequest;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\Configuration;
use App\Modules\Administration\Models\ConfigurationModel;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md
 * The one read path every other module's decided-default constant was
 * migrated to call (ADR-011 §4) — no consuming Service parses
 * setting_value itself.
 */
class ConfigurationService
{
    public function __construct(
        private readonly ConfigurationModel $configurationModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function getNumber(string $key): float
    {
        return (float) $this->requireConfiguration($key)->setting_value;
    }

    public function getString(string $key): string
    {
        return $this->requireConfiguration($key)->setting_value;
    }

    public function getBoolean(string $key): bool
    {
        return filter_var($this->requireConfiguration($key)->setting_value, FILTER_VALIDATE_BOOLEAN);
    }

    public function updateByKey(string $key, UpdateConfigurationRequest $request): ConfigurationResponse
    {
        $before = $this->requireConfiguration($key);

        if (! $before->is_editable) {
            throw new BusinessRuleException('CONFIGURATION_NOT_EDITABLE', 'This configuration setting is not editable.');
        }

        $this->configurationModel->update($before->setting_id, ['setting_value' => $request->settingValue]);
        $after = $this->configurationModel->findByKey($key);

        $this->auditService->record('Configuration', $after->setting_id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new ConfigurationResponse($after);
    }

    public function getConfiguration(string $key): ConfigurationResponse
    {
        return new ConfigurationResponse($this->requireConfiguration($key));
    }

    /**
     * @return list<ConfigurationResponse>
     */
    public function listByModule(string $module): array
    {
        return array_map(
            static fn (Configuration $configuration): ConfigurationResponse => new ConfigurationResponse($configuration),
            $this->configurationModel->findByModule($module),
        );
    }

    private function requireConfiguration(string $key): Configuration
    {
        $configuration = $this->configurationModel->findByKey($key);

        if ($configuration === null) {
            throw new BusinessRuleException('CONFIGURATION_NOT_FOUND', "Configuration key \"{$key}\" not found.");
        }

        return $configuration;
    }
}
