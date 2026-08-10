<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\Board;

class BoardResponse
{
    public readonly ?int $boardId;
    public readonly string $name;
    public readonly string $shortName;
    public readonly string $boardType;
    public readonly string $country;
    public readonly ?string $stateApplicability;
    public readonly string $status;
    public readonly ?string $description;

    public function __construct(Board $board)
    {
        $this->boardId            = $board->board_id;
        $this->name               = $board->name;
        $this->shortName          = $board->short_name;
        $this->boardType          = $board->board_type;
        $this->country            = $board->country;
        $this->stateApplicability = $board->state_applicability;
        $this->status             = $board->status;
        $this->description        = $board->description;
    }

    public function toArray(): array
    {
        return [
            'board_id'            => $this->boardId,
            'name'                => $this->name,
            'short_name'          => $this->shortName,
            'board_type'          => $this->boardType,
            'country'             => $this->country,
            'state_applicability' => $this->stateApplicability,
            'status'              => $this->status,
            'description'         => $this->description,
        ];
    }
}
