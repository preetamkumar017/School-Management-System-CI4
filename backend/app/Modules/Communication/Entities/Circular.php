<?php

declare(strict_types=1);

namespace App\Modules\Communication\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/communication/Phase-1-Domain-Model.md — ENT-COM-001.
 *
 * @property int|null $circular_id
 * @property int      $author_id
 * @property string   $post_type
 * @property string   $title
 * @property string   $body
 * @property string   $target_audience
 * @property string   $posted_at
 * @property string   $status
 */
class Circular extends BaseEntity
{
    public const POST_TYPE_HOMEWORK     = 'Homework';
    public const POST_TYPE_CIRCULAR     = 'Circular';
    public const POST_TYPE_ANNOUNCEMENT = 'Announcement';

    public const STATUS_POSTED    = 'Posted';
    public const STATUS_RETRACTED = 'Retracted';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'circular_id' => 'integer',
            'author_id'   => 'integer',
        ]);

        parent::__construct($data);
    }
}
