<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Attendance\DTOs\AttendanceCorrectionRequest;
use App\Modules\Attendance\DTOs\AttendancePercentageResponse;
use App\Modules\Attendance\DTOs\AttendanceRecordResponse;
use App\Modules\Attendance\DTOs\CreateAttendanceRecordRequest;
use App\Modules\Attendance\Entities\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceRecordModel;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\Entities\NotificationLog;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/attendance/Phase-3-Service-Controller-Design.md
 */
class AttendanceService
{
    public function __construct(
        private readonly AttendanceRecordModel $attendanceRecordModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
    ) {
    }

    public function markAttendance(CreateAttendanceRecordRequest $request): AttendanceRecordResponse
    {
        AppServices::studentService()->getStudent($request->studentId);
        $entry = AppServices::timetableEntryService()->getEntry($request->timetableEntryId);

        if ($entry->status !== 'PUBLISHED') {
            throw new BusinessRuleException(
                'TIMETABLE_ENTRY_NOT_PUBLISHED',
                'Attendance can only be marked against a published timetable entry.',
            );
        }

        if ($this->attendanceRecordModel->existsByStudentEntryDate($request->studentId, $request->timetableEntryId, $request->attendanceDate)) {
            throw new BusinessRuleException(
                'ATTENDANCE_ALREADY_MARKED',
                'Attendance for this student/period/date already exists (BR-ATT-001).',
            );
        }

        $id = $this->attendanceRecordModel->insert([
            'student_id'         => $request->studentId,
            'timetable_entry_id' => $request->timetableEntryId,
            'attendance_date'    => $request->attendanceDate,
            'state'              => $request->state,
            'marked_by'          => RequestContext::userId(),
        ], true);

        $record = $this->attendanceRecordModel->find($id);

        $this->auditService->record('AttendanceRecord', $id, AuditLog::ACTION_CREATE, null, $record->toRawArray());

        if ($request->state === AttendanceRecord::STATE_ABSENT) {
            $this->notifyGuardianOfAbsence($request->studentId);
        }

        return new AttendanceRecordResponse($record);
    }

    /**
     * BR-ATT-004 absence notification — logging half only, no live
     * dispatch (ADR-006 §9, closed by ADR-010 §3). Silently skipped if
     * the student has no linked guardian — this must never block
     * attendance marking itself.
     */
    private function notifyGuardianOfAbsence(int $studentId): void
    {
        $links = AppServices::studentGuardianLinkService()->listGuardiansForStudent($studentId);

        if ($links === []) {
            return;
        }

        $primary = null;

        foreach ($links as $link) {
            if ($link->isPrimaryContact) {
                $primary = $link;

                break;
            }
        }

        $guardianId = ($primary ?? $links[0])->guardianId;

        AppServices::notificationLogService()->create(new CreateNotificationLogRequest(
            NotificationLog::RECIPIENT_GUARDIAN,
            $guardianId,
            NotificationLog::CHANNEL_SMS,
            'BR-ATT-004 absence alert',
            'Your child was marked absent today. Please contact the school if this is unexpected.',
        ));
    }

    public function lockAttendance(int $id): AttendanceRecordResponse
    {
        $before = $this->requireRecord($id);

        if ($before->is_locked) {
            throw new BusinessRuleException('ATTENDANCE_ALREADY_LOCKED', 'This attendance record is already locked.');
        }

        $this->attendanceRecordModel->update($id, ['is_locked' => true]);
        $after = $this->attendanceRecordModel->find($id);

        $this->auditService->record('AttendanceRecord', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new AttendanceRecordResponse($after);
    }

    /**
     * ADR-006 §8 / ADR-011 §4 — within the configured edit window
     * (attendance.edit_window_days, default 0 = same day only),
     * correction is direct; beyond it, a reason is required and logged
     * as an override.
     */
    public function correctAttendance(int $id, AttendanceCorrectionRequest $request): AttendanceRecordResponse
    {
        $before = $this->requireRecord($id);

        $editWindowDays = (int) $this->configurationService->getNumber('attendance.edit_window_days');
        $attendanceDate = new \DateTimeImmutable((string) $before->attendance_date);
        $today          = new \DateTimeImmutable(Time::now()->toDateString());
        $daysSince      = $today > $attendanceDate ? $today->diff($attendanceDate)->days : 0;
        $withinWindow   = $daysSince <= $editWindowDays;

        if (! $withinWindow && ($request->reason === null || trim($request->reason) === '')) {
            throw new ValidationException(
                ['reason' => 'reason is required when correcting attendance beyond the configured edit window.'],
            );
        }

        $this->attendanceRecordModel->update($id, ['state' => $request->state]);
        $after = $this->attendanceRecordModel->find($id);

        if ($withinWindow) {
            $this->auditService->record('AttendanceRecord', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());
        } else {
            $this->auditService->record(
                'AttendanceRecord',
                $id,
                AuditLog::ACTION_OVERRIDE,
                $before->toRawArray(),
                $after->toRawArray(),
                $request->reason,
            );
        }

        return new AttendanceRecordResponse($after);
    }

    public function getAttendanceRecord(int $id): AttendanceRecordResponse
    {
        return new AttendanceRecordResponse($this->requireRecord($id));
    }

    /**
     * @return list<AttendanceRecordResponse>
     */
    public function listByTimetableEntryAndDate(int $timetableEntryId, string $date): array
    {
        return array_map(
            static fn (AttendanceRecord $record): AttendanceRecordResponse => new AttendanceRecordResponse($record),
            $this->attendanceRecordModel->findByTimetableEntryAndDate($timetableEntryId, $date),
        );
    }

    /**
     * FR-13: percentage = (PRESENT + LATE) / total marked records in
     * range. Days never marked are excluded from the denominator, not
     * treated as absent.
     */
    public function calculateAttendancePercentage(int $studentId, string $fromDate, string $toDate): AttendancePercentageResponse
    {
        $records = $this->attendanceRecordModel->findByStudentBetween($studentId, $fromDate, $toDate);

        if ($records === []) {
            return new AttendancePercentageResponse($studentId, $fromDate, $toDate, 100.0, false);
        }

        $presentOrLate = 0;

        foreach ($records as $record) {
            if (in_array($record->state, [AttendanceRecord::STATE_PRESENT, AttendanceRecord::STATE_LATE], true)) {
                $presentOrLate++;
            }
        }

        $percentage = round(($presentOrLate / count($records)) * 100, 2);

        return new AttendancePercentageResponse(
            $studentId,
            $fromDate,
            $toDate,
            $percentage,
            $percentage < $this->configurationService->getNumber('attendance.exam_eligibility_min_percentage'),
        );
    }

    /**
     * BR-ATT-006 — called by Examination's MarksRecordService (ADR-006
     * §11, closing the seam ADR-005 §2 left open).
     */
    public function isExamEligibilityAtRisk(int $studentId, string $fromDate, string $toDate): bool
    {
        return $this->calculateAttendancePercentage($studentId, $fromDate, $toDate)->isExamEligibilityAtRisk;
    }

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Attendance overview (report
     * area 2): Reports composes this instead of touching
     * AttendanceRecordModel directly (ADR-010 §7). Reuses the exact
     * PRESENT/LATE-counts-as-present definition and
     * attendance.exam_eligibility_min_percentage threshold
     * calculateAttendancePercentage() already established for FR-13/
     * BR-ATT-006 — never recomputed with different logic.
     *
     * @return array{
     *     schoolWide: array{present: int, total: int},
     *     byClass: array<int, array{present: int, total: int}>,
     *     byStudent: array<int, array{present: int, total: int}>,
     *     threshold: float,
     * }
     */
    public function getAttendanceOverviewData(string $fromDate, string $toDate): array
    {
        return [
            'schoolWide' => $this->attendanceRecordModel->countStatesForRange($fromDate, $toDate),
            'byClass'    => $this->attendanceRecordModel->countStatesForRangeGroupedByClass($fromDate, $toDate),
            'byStudent'  => $this->attendanceRecordModel->countStatesForRangeGroupedByStudent($fromDate, $toDate),
            'threshold'  => $this->configurationService->getNumber('attendance.exam_eligibility_min_percentage'),
        ];
    }

    private function requireRecord(int $id): AttendanceRecord
    {
        $record = $this->attendanceRecordModel->find($id);

        if ($record === null) {
            throw new BusinessRuleException('ATTENDANCE_RECORD_NOT_FOUND', 'Attendance record not found.');
        }

        return $record;
    }
}
