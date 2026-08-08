<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\Entities\NotificationLog;
use App\Modules\Timetable\DTOs\CreateSubstitutionRequest;
use App\Modules\Timetable\DTOs\SubstitutionResponse;
use App\Modules\Timetable\Entities\Substitution;
use App\Modules\Timetable\Entities\TimetableEntry;
use App\Modules\Timetable\Models\SubjectTeacherEligibilityModel;
use App\Modules\Timetable\Models\SubstitutionModel;
use App\Modules\Timetable\Models\TimetableEntryModel;
use Config\Services as AppServices;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md (FR-16 / BR-TT-004,
 * ADR-013). A Substitution applies "for that date only" — it never
 * touches TimetableEntry's row or version_no, which stays BR-TT-005's
 * revise() mechanism alone (ADR-013 §3).
 *
 * RBAC (ADR-024 §3, Phase 2): `timetable.manage` (Tier 1 only) gates writes.
 */
class SubstitutionService
{
    public const PERMISSION_MANAGE = 'timetable.manage';

    public function __construct(
        private readonly SubstitutionModel $substitutionModel,
        private readonly TimetableEntryModel $timetableEntryModel,
        private readonly SubjectTeacherEligibilityModel $subjectTeacherEligibilityModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createSubstitution(CreateSubstitutionRequest $request): SubstitutionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $entry = $this->requireEntry($request->timetableEntryId);

        if ($entry->status !== TimetableEntry::STATUS_PUBLISHED) {
            throw new BusinessRuleException(
                'TIMETABLE_ENTRY_NOT_PUBLISHED',
                'Only a PUBLISHED timetable entry can have a substitution.',
            );
        }

        if ($this->substitutionModel->existsByEntryDate($entry->timetable_entry_id, $request->substitutionDate)) {
            throw new BusinessRuleException(
                'SUBSTITUTION_ALREADY_EXISTS',
                'A substitution already exists for this timetable entry on this date.',
            );
        }

        // BR-TT-004: the original teacher must actually be recorded
        // absent (Unauthorized or On Leave) that date before a
        // substitution may be created at all.
        if (! AppServices::staffAttendanceService()->wasAbsentOn($entry->employee_id, $request->substitutionDate)) {
            throw new BusinessRuleException(
                'TEACHER_NOT_ABSENT',
                'The originally assigned teacher has no Unauthorized/On Leave attendance record for this date.',
            );
        }

        $substituteEmployeeId = $request->substituteEmployeeId;

        if ($substituteEmployeeId !== null) {
            $this->assertEligibleAndAvailable($substituteEmployeeId, $entry);
        } else {
            $substituteEmployeeId = $this->findFirstEligibleAndAvailable($entry);
        }

        // ADR-013 §1 (decided fallback): no eligible substitute ->
        // UNSUPERVISED, pending manual resolution — never a rejected
        // request. An explicitly-supplied but ineligible substitute is
        // still a hard reject (assertEligibleAndAvailable above).
        $status = $substituteEmployeeId !== null ? Substitution::STATUS_ASSIGNED : Substitution::STATUS_UNSUPERVISED;

        $id = $this->substitutionModel->insert([
            'timetable_entry_id'      => $entry->timetable_entry_id,
            'absent_employee_id'      => $entry->employee_id,
            'substitute_employee_id'  => $substituteEmployeeId,
            'substitution_date'       => $request->substitutionDate,
            'status'                  => $status,
        ], true);

        $substitution = $this->substitutionModel->find($id);

        $this->auditService->record('Substitution', $id, AuditLog::ACTION_CREATE, null, $substitution->toRawArray());

        // FR-16 "students/parents get notified" — log-only, no live
        // dispatch (ADR-010 §3 precedent, reused by ADR-013 §4).
        // NotificationLogService::create() validates recipient_ref_id
        // against a real entity of recipient_type, and Timetable has no
        // guardian-enumeration dependency to produce real Guardian ids
        // for a section's students (that lookup lives in SIS, which
        // Timetable has never depended on). Rather than fabricate a
        // fake recipient id, this logs to the real Employee on the
        // other side of the transaction — the substitute when
        // ASSIGNED, the originally-absent teacher when UNSUPERVISED —
        // both real, already-validated employee_ids (ADR-013 §4).
        AppServices::notificationLogService()->createInternal(new CreateNotificationLogRequest(
            NotificationLog::RECIPIENT_EMPLOYEE,
            $status === Substitution::STATUS_ASSIGNED ? $substituteEmployeeId : $entry->employee_id,
            NotificationLog::CHANNEL_EMAIL,
            $status === Substitution::STATUS_ASSIGNED ? 'FR-16 substitution assigned' : 'FR-16 period unsupervised',
            $status === Substitution::STATUS_ASSIGNED
                ? "You have been assigned as substitute teacher for period {$entry->period_no} ({$entry->day_of_week}) on {$request->substitutionDate}."
                : "No eligible substitute was found for your period {$entry->period_no} ({$entry->day_of_week}) on {$request->substitutionDate}. It remains unsupervised pending manual resolution.",
        ));

        return new SubstitutionResponse($substitution);
    }

    public function getSubstitution(int $id): SubstitutionResponse
    {
        $substitution = $this->substitutionModel->find($id);

        if ($substitution === null) {
            throw new BusinessRuleException('SUBSTITUTION_NOT_FOUND', 'Substitution not found.');
        }

        return new SubstitutionResponse($substitution);
    }

    /**
     * @return list<int> employee_ids eligible for this entry's subject
     *                    and not already booked that day/period
     */
    public function listEligibleSubstitutes(int $timetableEntryId): array
    {
        $entry = $this->requireEntry($timetableEntryId);

        $eligibleIds = $this->subjectTeacherEligibilityModel->findEmployeeIdsBySubject($entry->subject_id);

        return array_values(array_filter(
            $eligibleIds,
            fn (int $employeeId): bool => $employeeId !== $entry->employee_id
                && ! $this->timetableEntryModel->existsByTeacherDayPeriod($employeeId, $entry->day_of_week, $entry->period_no),
        ));
    }

    private function assertEligibleAndAvailable(int $substituteEmployeeId, TimetableEntry $entry): void
    {
        AppServices::employeeService()->getEmployee($substituteEmployeeId);

        if (! $this->subjectTeacherEligibilityModel->existsByEmployeeSubject($substituteEmployeeId, $entry->subject_id)) {
            throw new BusinessRuleException(
                'SUBSTITUTE_NOT_ELIGIBLE',
                'This employee is not recorded as eligible to teach this subject (BR-TT-004).',
            );
        }

        if ($this->timetableEntryModel->existsByTeacherDayPeriod($substituteEmployeeId, $entry->day_of_week, $entry->period_no)) {
            throw new BusinessRuleException(
                'SUBSTITUTE_NOT_AVAILABLE',
                'This employee already has another timetable entry for this day and period.',
            );
        }
    }

    private function findFirstEligibleAndAvailable(TimetableEntry $entry): ?int
    {
        $candidates = $this->listEligibleSubstitutes($entry->timetable_entry_id);

        return $candidates[0] ?? null;
    }

    private function requireEntry(int $id): TimetableEntry
    {
        $entry = $this->timetableEntryModel->find($id);

        if ($entry === null) {
            throw new BusinessRuleException('TIMETABLE_ENTRY_NOT_FOUND', 'Timetable entry not found.');
        }

        return $entry;
    }
}
