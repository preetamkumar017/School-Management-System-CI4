<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — ENT-SYS-001.
 *
 * @property int|null    $user_id
 * @property string      $username
 * @property string      $password_hash
 * @property int         $role_id
 * @property string      $owner_type
 * @property int         $owner_ref_id
 * @property string      $status
 * @property int                       $failed_login_count
 * @property \CodeIgniter\I18n\Time|null $last_login_at
 */
class User extends BaseEntity
{
    public const STATUS_ACTIVE      = 'ACTIVE';
    public const STATUS_LOCKED      = 'LOCKED';
    public const STATUS_DEACTIVATED = 'DEACTIVATED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        // Merge onto BaseEntity's common casts/dates rather than
        // redeclaring them here, which would silently drop the parent's
        // entries — PHP property defaults don't merge across inheritance.
        $this->casts = array_merge($this->casts, [
            'user_id'            => 'integer',
            'role_id'            => 'integer',
            'owner_ref_id'       => 'integer',
            'failed_login_count' => 'integer',
        ]);

        $this->dates = array_merge($this->dates, ['last_login_at']);

        parent::__construct($data);
    }
}
