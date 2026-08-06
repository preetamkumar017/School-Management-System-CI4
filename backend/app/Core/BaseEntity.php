<?php

declare(strict_types=1);

namespace App\Core;

use CodeIgniter\Entity\Entity;

/**
 * Every product Entity extends this (Company Development Standard §4.4).
 * Declares the common audit/soft-delete columns' types so they come back
 * as the right PHP type without every Entity repeating the same casts.
 * Junction tables are the documented exception (no surrogate PK, no
 * audit columns) — they extend CodeIgniter\Entity\Entity directly instead.
 */
class BaseEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'created_by' => '?integer',
        'updated_by' => '?integer',
        'is_deleted' => 'boolean',
        'deleted_by' => '?integer',
    ];
}
