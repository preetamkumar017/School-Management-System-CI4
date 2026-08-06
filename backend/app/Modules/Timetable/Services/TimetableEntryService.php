<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Timetable\DTOs\CreateTimetableEntryRequest;
use App\Modules\Timetable\DTOs\TimetableEntryResponse;
use App\Modules\Timetable\Entities\TimetableEntry;
use App\Modules\Timetable\Models\TimetableEntryModel;
use Config\Services as AppServices;

/**
 * docs/design/timetable/Phase-3-Service-Controller-Design.md
 */
class TimetableEntryService
{
    /**
     * ADR-006 §7 — decided default; "Client/Product Decision Required"
     * per Appendix-C, pending a future Configuration entity.
     */
    private const WEEKLY_LOAD_CEILING = 30;

    public function __construct(
        private readonly TimetableEntryModel $timetableEntryModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createEntry(CreateTimetableEntryRequest $request): TimetableEntryResponse
    {
        AppServices::sectionService()->getSection($request->sectionId);
        AppServices::subjectService()->getSubject($request->subjectId);

        $this->assertNoConflicts($request, null);

        $id = $this->timetableEntryModel->insert([
            'section_id'  => $request->sectionId,
            'subject_id'  => $request->subjectId,
            'employee_id' => $request->employeeId,
            'day_of_week' => $request->dayOfWeek,
            'period_no'   => $request->periodNo,
            'room_id'     => $request->roomId,
            'version_no'  => 1,
            'status'      => TimetableEntry::STATUS_DRAFT,
        ], true);

        $entry = $this->timetableEntryModel->find($id);

        $this->auditService->record('TimetableEntry', $id, AuditLog::ACTION_CREATE, null, $entry->toRawArray());

        return new TimetableEntryResponse($entry);
    }

    public function publishEntry(int $id): TimetableEntryResponse
    {
        $before = $this->requireEntry($id);

        if ($before->status !== TimetableEntry::STATUS_DRAFT) {
            throw new BusinessRuleException(
                'TIMETABLE_ENTRY_INVALID_STATUS_TRANSITION',
                "Cannot publish a timetable entry in status {$before->status}.",
            );
        }

        $this->timetableEntryModel->update($id, ['status' => TimetableEntry::STATUS_PUBLISHED]);
        $after = $this->timetableEntryModel->find($id);

        $this->auditService->record('TimetableEntry', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new TimetableEntryResponse($after);
    }

    /**
     * BR-TT-005 — decided (Phase 3): mutates the row in place and
     * increments version_no, rather than a parallel history row.
     */
    public function reviseEntry(int $id, CreateTimetableEntryRequest $request): TimetableEntryResponse
    {
        $before = $this->requireEntry($id);

        if ($before->status !== TimetableEntry::STATUS_PUBLISHED) {
            throw new BusinessRuleException(
                'TIMETABLE_ENTRY_NOT_PUBLISHED',
                'Only a PUBLISHED entry can be revised — edit a DRAFT entry directly instead.',
            );
        }

        AppServices::sectionService()->getSection($request->sectionId);
        AppServices::subjectService()->getSubject($request->subjectId);

        $this->assertNoConflicts($request, $id);

        $this->timetableEntryModel->update($id, [
            'section_id'  => $request->sectionId,
            'subject_id'  => $request->subjectId,
            'employee_id' => $request->employeeId,
            'day_of_week' => $request->dayOfWeek,
            'period_no'   => $request->periodNo,
            'room_id'     => $request->roomId,
            'version_no'  => $before->version_no + 1,
        ]);

        $after = $this->timetableEntryModel->find($id);

        $this->auditService->record('TimetableEntry', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new TimetableEntryResponse($after);
    }

    public function getEntry(int $id): TimetableEntryResponse
    {
        return new TimetableEntryResponse($this->requireEntry($id));
    }

    /**
     * @return list<TimetableEntryResponse>
     */
    public function listEntriesBySection(int $sectionId): array
    {
        return array_map(
            static fn (TimetableEntry $entry): TimetableEntryResponse => new TimetableEntryResponse($entry),
            $this->timetableEntryModel->findBySectionId($sectionId),
        );
    }

    private function assertNoConflicts(CreateTimetableEntryRequest $request, ?int $exceptId): void
    {
        $teacherConflict = $exceptId === null
            ? $this->timetableEntryModel->existsByTeacherDayPeriod($request->employeeId, $request->dayOfWeek, $request->periodNo)
            : $this->timetableEntryModel->existsByTeacherDayPeriodExceptId($request->employeeId, $request->dayOfWeek, $request->periodNo, $exceptId);

        if ($teacherConflict) {
            throw new BusinessRuleException(
                'TEACHER_DOUBLE_BOOKED',
                'This teacher already has an entry for this day and period (BR-TT-001).',
            );
        }

        $sectionConflict = $exceptId === null
            ? $this->timetableEntryModel->existsBySectionDayPeriod($request->sectionId, $request->dayOfWeek, $request->periodNo)
            : $this->timetableEntryModel->existsBySectionDayPeriodExceptId($request->sectionId, $request->dayOfWeek, $request->periodNo, $exceptId);

        if ($sectionConflict) {
            throw new BusinessRuleException(
                'SECTION_DOUBLE_BOOKED',
                'This section already has an entry for this day and period.',
            );
        }

        if ($request->roomId !== null) {
            $roomConflict = $exceptId === null
                ? $this->timetableEntryModel->existsByRoomDayPeriod($request->roomId, $request->dayOfWeek, $request->periodNo)
                : $this->timetableEntryModel->existsByRoomDayPeriodExceptId($request->roomId, $request->dayOfWeek, $request->periodNo, $exceptId);

            if ($roomConflict) {
                throw new BusinessRuleException(
                    'ROOM_DOUBLE_BOOKED',
                    'This room is already booked for this day and period (BR-TT-002).',
                );
            }
        }

        $currentLoad = $exceptId === null
            ? $this->timetableEntryModel->countByEmployeeId($request->employeeId)
            : $this->timetableEntryModel->countByEmployeeIdExceptId($request->employeeId, $exceptId);

        if ($currentLoad >= self::WEEKLY_LOAD_CEILING) {
            throw new BusinessRuleException(
                'WEEKLY_LOAD_CEILING_EXCEEDED',
                'This teacher is already at the configured weekly teaching load ceiling (BR-TT-006).',
            );
        }
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
