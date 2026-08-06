<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\AcademicSession;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 */
class AcademicSessionModel extends BaseModel
{
    protected $table          = 'academic_sessions';
    protected $primaryKey     = 'academic_session_id';
    protected $returnType     = AcademicSession::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'session_name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'updated_by',
    ];

    public function findBySessionName(string $value): ?AcademicSession
    {
        return $this->where('session_name', $value)->first();
    }

    public function existsBySessionName(string $value): bool
    {
        return $this->where('session_name', $value)->countAllResults() > 0;
    }

    public function existsBySessionNameExceptId(string $value, int $id): bool
    {
        return $this->where('session_name', $value)->where('academic_session_id !=', $id)->countAllResults() > 0;
    }

    /**
     * @return list<AcademicSession>
     */
    public function findOverlapping(string $startDate, string $endDate, ?int $exceptId = null): array
    {
        $builder = $this->where('start_date <=', $endDate)->where('end_date >=', $startDate);

        if ($exceptId !== null) {
            $builder = $builder->where('academic_session_id !=', $exceptId);
        }

        return $builder->findAll();
    }

    /**
     * @return list<AcademicSession>
     */
    public function findByStatus(string $status): array
    {
        return $this->where('status', $status)->findAll();
    }
}
