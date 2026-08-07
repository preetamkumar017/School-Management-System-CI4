<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\Configuration;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md
 */
class ConfigurationModel extends BaseModel
{
    protected $table          = 'configurations';
    protected $primaryKey     = 'setting_id';
    protected $returnType     = Configuration::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'data_type',
        'module',
        'is_editable',
        'created_by',
        'updated_by',
    ];

    public function findByKey(string $key): ?Configuration
    {
        return $this->where('setting_key', $key)->first();
    }

    /**
     * @return list<Configuration>
     */
    public function findByModule(string $module): array
    {
        return $this->where('module', $module)->findAll();
    }
}
