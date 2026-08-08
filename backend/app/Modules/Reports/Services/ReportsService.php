<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Excel\ExcelRenderer;
use App\Core\Pdf\PdfRenderer;
use App\Modules\Reports\DTOs\AcademicPerformanceResponse;
use App\Modules\Reports\DTOs\AdmissionsFunnelResponse;
use App\Modules\Reports\DTOs\AttendanceOverviewResponse;
use App\Modules\Reports\DTOs\FeeCollectionSummaryResponse;
use App\Modules\Reports\DTOs\SummaryResponse;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/reports/Phase-1-Service-Controller-Design.md
 * docs/ADR/ADR-022-reports-dashboard.md
 * Pure composition over other modules' Services, never their Models
 * directly (Company Development Standard's cross-module rule) — no
 * entity, no Model of its own (ADR-010 §7, unreversed by ADR-022).
 *
 * RBAC (ADR-024 §3, Phase 2): `reports.manage` (Tier 1 only) gates every
 * method here, including reads — see this phase's ADR-024 Addendum for
 * why Reports' reads are gated even though most other modules' reads
 * stay open: aggregate fee-collection/attendance/academic-performance
 * data spans every student/employee in the school at once, a materially
 * more sensitive exposure than a single Class/Book/Route list.
 */
class ReportsService
{
    public const PERMISSION_MANAGE = 'reports.manage';

    /**
     * ADR-022 §"Academic performance" — no existing pass/fail concept is
     * modeled on GradingScheme/ReportCard; a GPA of 4.0 (equivalent to the
     * 40% mark the grade-point formula in
     * ExamService::recalculateReportCards already derives grade points
     * from, since grade_point = percentage / 10) is used as a defensible,
     * documented default. This is a Reports-only interpretation of
     * already-stored ReportCard.gpa values, not a new field/column on
     * ReportCard or GradingScheme, and not a different GPA computation.
     */
    private const PASS_THRESHOLD_GPA = 4.0;

    public function __construct(
        private readonly PdfRenderer $pdfRenderer,
        private readonly ExcelRenderer $excelRenderer,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function getSummary(): SummaryResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $books = AppServices::bookService()->listBooks();

        return new SummaryResponse(
            Time::now()->toDateTimeString(),
            AppServices::userService()->countUsers(),
            count(AppServices::classService()->listClasses()),
            count(AppServices::academicSessionService()->listSessions()),
            AppServices::departmentService()->countDepartments(),
            AppServices::designationService()->countDesignations(),
            AppServices::employeeService()->countEmployees(),
            count($books),
            count(array_filter($books, static fn ($book): bool => $book->isAvailable)),
            count(AppServices::vehicleService()->listVehicles()),
            count(AppServices::routeService()->listRoutes()),
            count(AppServices::feeHeadService()->listFeeHeads()),
        );
    }

    /**
     * ADR-022 report area 1 — Fee collection summary. Composes
     * InvoiceService::getOutstandingSummaryForSession() and
     * PaymentService::getCollectedSummaryForSession(), both new,
     * narrowly-scoped aggregate methods added to Fees (the owning
     * module) for this area specifically.
     */
    public function getFeeCollectionSummary(int $academicSessionId): FeeCollectionSummaryResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        AppServices::academicSessionService()->getSession($academicSessionId);

        $collected   = AppServices::paymentService()->getCollectedSummaryForSession($academicSessionId);
        $outstanding = AppServices::invoiceService()->getOutstandingSummaryForSession($academicSessionId);

        return new FeeCollectionSummaryResponse(
            $academicSessionId,
            $collected['total'],
            $outstanding['total'],
            $collected['byClass'],
            $outstanding['byClass'],
            $outstanding['defaulterCount'],
            Time::now()->toDateTimeString(),
        );
    }

    /**
     * ADR-022 report area 2 — Attendance overview. Composes
     * AttendanceService::getAttendanceOverviewData(), one new aggregate
     * method added to Attendance (the owning module), reusing the exact
     * PRESENT/LATE-as-present definition and
     * attendance.exam_eligibility_min_percentage threshold FR-13/
     * BR-ATT-006 already established.
     */
    public function getAttendanceOverview(string $fromDate, string $toDate): AttendanceOverviewResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $data      = AppServices::attendanceService()->getAttendanceOverviewData($fromDate, $toDate);
        $threshold = $data['threshold'];

        $schoolWidePercentage = $this->percentageOf($data['schoolWide']['present'], $data['schoolWide']['total']);

        $percentageByClass = [];

        foreach ($data['byClass'] as $classId => $counts) {
            $percentageByClass[$classId] = $this->percentageOf($counts['present'], $counts['total']);
        }

        $studentsBelowThreshold = [];

        foreach ($data['byStudent'] as $studentId => $counts) {
            $percentage = $this->percentageOf($counts['present'], $counts['total']);

            if ($percentage < $threshold) {
                $studentsBelowThreshold[] = ['student_id' => $studentId, 'percentage' => $percentage];
            }
        }

        return new AttendanceOverviewResponse(
            $fromDate,
            $toDate,
            $schoolWidePercentage,
            $percentageByClass,
            $studentsBelowThreshold,
            $threshold,
            Time::now()->toDateTimeString(),
        );
    }

    /**
     * ADR-022 report area 3 — Admissions funnel. Composes
     * SeatAllocationService::listForAcademicSession() (an existing-shape
     * listing, not a new aggregate) and
     * ApplicationService::getStatusCountsForClassIds() (one new aggregate
     * method added to Admission, the owning module). Application has no
     * academic_session_id of its own (Appendix-G) — "this session's
     * applications" is resolved via the classes that have a
     * SeatAllocation for this session, the same class+session resolution
     * ApplicationService::confirmEnrollment already uses.
     */
    public function getAdmissionsFunnel(int $academicSessionId): AdmissionsFunnelResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        AppServices::academicSessionService()->getSession($academicSessionId);

        $seatAllocations = AppServices::seatAllocationService()->listForAcademicSession($academicSessionId);
        $classIds        = array_values(array_unique(array_map(static fn ($seatAllocation) => $seatAllocation->classId, $seatAllocations)));

        $countsByStatus = AppServices::applicationService()->getStatusCountsForClassIds($classIds);

        $occupancy = array_map(
            static fn ($seatAllocation): array => [
                'class_id'              => $seatAllocation->classId,
                'total_capacity'        => $seatAllocation->totalCapacity,
                'seats_filled'          => $seatAllocation->seatsFilled,
                'occupancy_percentage'  => $seatAllocation->totalCapacity > 0
                    ? round(($seatAllocation->seatsFilled / $seatAllocation->totalCapacity) * 100, 2)
                    : 0.0,
            ],
            $seatAllocations,
        );

        return new AdmissionsFunnelResponse($academicSessionId, $countsByStatus, $occupancy, Time::now()->toDateTimeString());
    }

    /**
     * ADR-022 report area 4 — Academic performance. Composes
     * ReportCardService::listReportCardsByExam() — already existing, no
     * new aggregate method needed on Examination at all, since every
     * figure here (average GPA, pass/fail, rank distribution) is derived
     * in-memory from ReportCard.gpa/class_rank exactly as
     * ExamService::recalculateReportCards already computed and stored
     * them (Stage 6a's decided GPA formula / standard-competition rank
     * convention — never recomputed differently here).
     */
    public function getAcademicPerformance(int $examId): AcademicPerformanceResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $reportCards = AppServices::reportCardService()->listReportCardsByExamForCrossModuleRead($examId);

        $count = count($reportCards);

        if ($count === 0) {
            return new AcademicPerformanceResponse($examId, 0, 0.0, 0, 0, self::PASS_THRESHOLD_GPA, [], Time::now()->toDateTimeString());
        }

        $totalGpa  = 0.0;
        $passCount = 0;
        $rankDist  = [];

        foreach ($reportCards as $reportCard) {
            $totalGpa += $reportCard->gpa;

            if ($reportCard->gpa >= self::PASS_THRESHOLD_GPA) {
                $passCount++;
            }

            if ($reportCard->classRank !== null) {
                $rankDist[$reportCard->classRank] = ($rankDist[$reportCard->classRank] ?? 0) + 1;
            }
        }

        ksort($rankDist);

        return new AcademicPerformanceResponse(
            $examId,
            $count,
            round($totalGpa / $count, 2),
            $passCount,
            $count - $passCount,
            self::PASS_THRESHOLD_GPA,
            $rankDist,
            Time::now()->toDateTimeString(),
        );
    }

    /**
     * ADR-022 §5 — reuses PdfRenderer/dompdf exactly as
     * InvoiceService::generateInvoicePdf / ReportCardService::generatePdf
     * already do: plain HTML table(s) rendered to PDF bytes. Returned
     * directly to the caller (Controller streams it), not persisted via
     * DocumentService — Reports has no owning entity for a Document
     * record to belong to (ADR-010 §7), and these exports are point-in-
     * time/regenerable, not retained artifacts.
     */
    public function renderPdf(string $title, array $headers, array $rows): string
    {
        $headerCells = implode('', array_map(static fn ($header): string => '<th>' . htmlspecialchars((string) $header) . '</th>', $headers));

        $bodyRows = '';

        foreach ($rows as $row) {
            $cells = implode('', array_map(static fn ($cell): string => '<td>' . htmlspecialchars((string) $cell) . '</td>', $row));
            $bodyRows .= '<tr>' . $cells . '</tr>';
        }

        $html = '<html><body>'
            . '<h2>' . htmlspecialchars($title) . '</h2>'
            . '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%;">'
            . '<thead><tr>' . $headerCells . '</tr></thead>'
            . '<tbody>' . $bodyRows . '</tbody>'
            . '</table>'
            . '</body></html>';

        return $this->pdfRenderer->render($html);
    }

    /**
     * ADR-022 §6 — reuses ExcelRenderer/PhpSpreadsheet, one sheet per
     * call, mirroring renderPdf()'s shape.
     */
    public function renderExcel(string $title, array $headers, array $rows): string
    {
        return $this->excelRenderer->render($title, $headers, $rows);
    }

    private function percentageOf(int $present, int $total): float
    {
        return $total === 0 ? 100.0 : round(($present / $total) * 100, 2);
    }
}
