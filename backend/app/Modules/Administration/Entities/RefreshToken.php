<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use CodeIgniter\Entity\Entity;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — supporting table,
 * not an Appendix-G entity. Extends CodeIgniter\Entity\Entity directly,
 * not App\Core\BaseEntity — no updated_at/deleted_at/audit columns.
 *
 * @property int|null    $refresh_token_id
 * @property int         $user_id
 * @property string      $token_hash
 * @property string      $expires_at
 * @property string|null $revoked_at
 * @property string      $created_at
 */
class RefreshToken extends Entity
{
    protected $dates = ['expires_at', 'revoked_at', 'created_at'];

    protected $casts = [
        'refresh_token_id' => 'integer',
        'user_id'          => 'integer',
    ];

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at !== null && $this->expires_at > new \DateTimeImmutable();
    }
}
