<?php

declare(strict_types=1);

namespace App\Modules\Library\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/library/Phase-1-Domain-Model.md — ENT-LIB-001.
 *
 * @property int|null $book_id
 * @property string   $barcode
 * @property string   $title
 * @property string|null $author
 * @property string   $classification
 * @property bool     $is_available
 */
class Book extends BaseEntity
{
    public const CLASSIFICATION_CIRCULATING = 'Circulating';
    public const CLASSIFICATION_REFERENCE   = 'Reference';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'book_id'      => 'integer',
            'is_available' => 'boolean',
        ]);

        parent::__construct($data);
    }
}
