<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

use App\Modules\Communication\Entities\Circular;

/**
 * docs/design/communication/Phase-2-Model-DTO-Design.md
 */
final class CircularResponse
{
    public readonly int $circularId;
    public readonly int $authorId;
    public readonly string $postType;
    public readonly string $title;
    public readonly string $body;
    public readonly string $targetAudience;
    public readonly string $postedAt;
    public readonly string $status;

    public function __construct(Circular $circular)
    {
        $this->circularId     = $circular->circular_id;
        $this->authorId       = $circular->author_id;
        $this->postType       = $circular->post_type;
        $this->title          = $circular->title;
        $this->body           = $circular->body;
        $this->targetAudience = $circular->target_audience;
        $this->postedAt       = $circular->posted_at;
        $this->status         = $circular->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'circular_id'     => $this->circularId,
            'author_id'       => $this->authorId,
            'post_type'       => $this->postType,
            'title'           => $this->title,
            'body'            => $this->body,
            'target_audience' => $this->targetAudience,
            'posted_at'       => $this->postedAt,
            'status'          => $this->status,
        ];
    }
}
