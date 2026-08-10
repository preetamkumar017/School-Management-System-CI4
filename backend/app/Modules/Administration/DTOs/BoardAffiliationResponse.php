<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\BoardAffiliation;

class BoardAffiliationResponse
{
    public readonly ?int $affiliationId;
    public readonly int $boardId;
    public readonly int $academicSessionId;
    public readonly string $affiliationNumber;
    public readonly ?string $validityStart;
    public readonly ?string $validityEnd;
    public readonly string $status;
    public readonly ?string $boardName;
    public readonly ?string $sessionName;

    public function __construct(BoardAffiliation $aff, ?string $boardName = null, ?string $sessionName = null)
    {
        $this->affiliationId      = $aff->affiliation_id;
        $this->boardId            = $aff->board_id;
        $this->academicSessionId  = $aff->academic_session_id;
        $this->affiliationNumber  = $aff->affiliation_number;
        $this->validityStart      = $aff->validity_start;
        $this->validityEnd        = $aff->validity_end;
        $this->status             = $aff->status;
        $this->boardName          = $boardName;
        $this->sessionName        = $sessionName;
    }

    public function toArray(): array
    {
        return [
            'affiliation_id'      => $this->affiliationId,
            'board_id'            => $this->boardId,
            'academic_session_id' => $this->academicSessionId,
            'affiliation_number'  => $this->affiliationNumber,
            'validity_start'      => $this->validityStart,
            'validity_end'        => $this->validityEnd,
            'status'              => $this->status,
            'board_name'          => $this->boardName,
            'session_name'        => $this->sessionName,
        ];
    }
}
