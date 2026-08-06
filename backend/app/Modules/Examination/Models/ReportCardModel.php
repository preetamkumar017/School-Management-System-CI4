<?php

declare(strict_types=1);

namespace App\Modules\Examination\Models;

use App\Core\BaseModel;
use App\Modules\Examination\Entities\ReportCard;

/**
 * docs/design/examination/Phase-2-Model-Design.md
 */
class ReportCardModel extends BaseModel
{
    protected $table          = 'report_cards';
    protected $primaryKey     = 'report_card_id';
    protected $returnType     = ReportCard::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'student_id',
        'exam_id',
        'grade_summary',
        'gpa',
        'class_rank',
        'is_published',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeGradeSummary'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeGradeSummary'];

    /**
     * Same reasoning as GradingSchemeModel::encodeGradeBandJson — the
     * Query Builder binds a raw PHP array as a tuple, not JSON, unless
     * encoded first.
     */
    protected function encodeGradeSummary(array $eventData): array
    {
        if (isset($eventData['data']['grade_summary']) && is_array($eventData['data']['grade_summary'])) {
            $eventData['data']['grade_summary'] = json_encode($eventData['data']['grade_summary']);
        }

        return $eventData;
    }

    public function findByStudentAndExam(int $studentId, int $examId): ?ReportCard
    {
        return $this->where('student_id', $studentId)->where('exam_id', $examId)->first();
    }

    /**
     * @return list<ReportCard>
     */
    public function findByExamId(int $examId): array
    {
        return $this->where('exam_id', $examId)->findAll();
    }
}
