<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md —
 * ENT-SYS-005.
 *
 * @property int|null $setting_id
 * @property string   $setting_key
 * @property string   $setting_value
 * @property string   $data_type
 * @property string   $module
 * @property bool     $is_editable
 */
class Configuration extends BaseEntity
{
    public const DATA_TYPE_STRING  = 'String';
    public const DATA_TYPE_NUMBER  = 'Number';
    public const DATA_TYPE_BOOLEAN = 'Boolean';
    public const DATA_TYPE_DATE    = 'Date';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'setting_id'  => 'integer',
            'is_editable' => 'boolean',
        ]);

        parent::__construct($data);
    }
}
