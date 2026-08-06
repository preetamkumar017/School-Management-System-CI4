<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\AcademicSession;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class AcademicSessionResponse
{
    public readonly int $academicSessionId;
    public readonly string $sessionName;
    public readonly string $startDate;
    public readonly string $endDate;
    public readonly string $status;

    public function __construct(AcademicSession $session)
    {
        $this->academicSessionId = $session->academic_session_id;
        $this->sessionName       = $session->session_name;
        $this->startDate         = (string) $session->start_date;
        $this->endDate           = (string) $session->end_date;
        $this->status            = $session->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'academic_session_id' => $this->academicSessionId,
            'session_name'        => $this->sessionName,
            'start_date'          => $this->startDate,
            'end_date'            => $this->endDate,
            'status'              => $this->status,
        ];
    }
}
