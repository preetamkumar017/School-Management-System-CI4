<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/reports/Phase-1-Service-Controller-Design.md
 * docs/ADR/ADR-022-reports-dashboard.md
 * Base path /api/v1/reports
 */
#[OA\Tag(name: 'Reports')]
class ReportsController extends BaseController
{
    #[OA\Get(
        path: '/reports/summary',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SummaryResponse'))],
    )]
    public function summary()
    {
        return $this->respondSuccess(Services::reportsService()->getSummary()->toArray());
    }

    #[OA\Get(
        path: '/reports/fee-collection',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.')],
    )]
    public function feeCollection()
    {
        return $this->respondSuccess(Services::reportsService()->getFeeCollectionSummary($this->requireAcademicSessionId())->toArray());
    }

    #[OA\Get(
        path: '/reports/fee-collection/pdf',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The PDF file stream.')],
    )]
    public function feeCollectionPdf()
    {
        $response = Services::reportsService()->getFeeCollectionSummary($this->requireAcademicSessionId());

        $headers = ['Class ID', 'Collected', 'Outstanding'];
        $classIds = array_unique(array_merge(array_keys($response->collectedByClass), array_keys($response->outstandingByClass)));
        $rows = array_map(static fn ($classId): array => [
            $classId,
            $response->collectedByClass[$classId] ?? 0.0,
            $response->outstandingByClass[$classId] ?? 0.0,
        ], $classIds);

        $bytes = Services::reportsService()->renderPdf('Fee Collection Summary', $headers, $rows);

        return $this->response->download('fee-collection-summary.pdf', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/fee-collection/excel',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The .xlsx file stream.')],
    )]
    public function feeCollectionExcel()
    {
        $response = Services::reportsService()->getFeeCollectionSummary($this->requireAcademicSessionId());

        $headers = ['Class ID', 'Collected', 'Outstanding'];
        $classIds = array_unique(array_merge(array_keys($response->collectedByClass), array_keys($response->outstandingByClass)));
        $rows = array_map(static fn ($classId): array => [
            $classId,
            $response->collectedByClass[$classId] ?? 0.0,
            $response->outstandingByClass[$classId] ?? 0.0,
        ], $classIds);

        $bytes = Services::reportsService()->renderExcel('Fee Collection Summary', $headers, $rows);

        return $this->response->download('fee-collection-summary.xlsx', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/attendance-overview',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK.')],
    )]
    public function attendanceOverview()
    {
        [$fromDate, $toDate] = $this->requireDateRange();

        return $this->respondSuccess(Services::reportsService()->getAttendanceOverview($fromDate, $toDate)->toArray());
    }

    #[OA\Get(
        path: '/reports/attendance-overview/pdf',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'The PDF file stream.')],
    )]
    public function attendanceOverviewPdf()
    {
        [$fromDate, $toDate] = $this->requireDateRange();
        $response = Services::reportsService()->getAttendanceOverview($fromDate, $toDate);

        $headers = ['Class ID', 'Attendance %'];
        $rows = [];

        foreach ($response->percentageByClass as $classId => $percentage) {
            $rows[] = [$classId, $percentage];
        }

        $bytes = Services::reportsService()->renderPdf('Attendance Overview', $headers, $rows);

        return $this->response->download('attendance-overview.pdf', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/attendance-overview/excel',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'The .xlsx file stream.')],
    )]
    public function attendanceOverviewExcel()
    {
        [$fromDate, $toDate] = $this->requireDateRange();
        $response = Services::reportsService()->getAttendanceOverview($fromDate, $toDate);

        $headers = ['Class ID', 'Attendance %'];
        $rows = [];

        foreach ($response->percentageByClass as $classId => $percentage) {
            $rows[] = [$classId, $percentage];
        }

        $bytes = Services::reportsService()->renderExcel('Attendance Overview', $headers, $rows);

        return $this->response->download('attendance-overview.xlsx', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/admissions-funnel',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.')],
    )]
    public function admissionsFunnel()
    {
        return $this->respondSuccess(Services::reportsService()->getAdmissionsFunnel($this->requireAcademicSessionId())->toArray());
    }

    #[OA\Get(
        path: '/reports/admissions-funnel/pdf',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The PDF file stream.')],
    )]
    public function admissionsFunnelPdf()
    {
        $response = Services::reportsService()->getAdmissionsFunnel($this->requireAcademicSessionId());

        $headers = ['Status', 'Count'];
        $rows = [];

        foreach ($response->countsByStatus as $status => $count) {
            $rows[] = [$status, $count];
        }

        $bytes = Services::reportsService()->renderPdf('Admissions Funnel', $headers, $rows);

        return $this->response->download('admissions-funnel.pdf', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/admissions-funnel/excel',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The .xlsx file stream.')],
    )]
    public function admissionsFunnelExcel()
    {
        $response = Services::reportsService()->getAdmissionsFunnel($this->requireAcademicSessionId());

        $headers = ['Status', 'Count'];
        $rows = [];

        foreach ($response->countsByStatus as $status => $count) {
            $rows[] = [$status, $count];
        }

        $bytes = Services::reportsService()->renderExcel('Admissions Funnel', $headers, $rows);

        return $this->response->download('admissions-funnel.xlsx', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/academic-performance',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.')],
    )]
    public function academicPerformance()
    {
        return $this->respondSuccess(Services::reportsService()->getAcademicPerformance($this->requireExamId())->toArray());
    }

    #[OA\Get(
        path: '/reports/academic-performance/pdf',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The PDF file stream.')],
    )]
    public function academicPerformancePdf()
    {
        $response = Services::reportsService()->getAcademicPerformance($this->requireExamId());

        $headers = ['Class Rank', 'Students'];
        $rows = [];

        foreach ($response->rankDistribution as $rank => $studentCount) {
            $rows[] = [$rank, $studentCount];
        }

        $bytes = Services::reportsService()->renderPdf('Academic Performance', $headers, $rows);

        return $this->response->download('academic-performance.pdf', $bytes, true);
    }

    #[OA\Get(
        path: '/reports/academic-performance/excel',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The .xlsx file stream.')],
    )]
    public function academicPerformanceExcel()
    {
        $response = Services::reportsService()->getAcademicPerformance($this->requireExamId());

        $headers = ['Class Rank', 'Students'];
        $rows = [];

        foreach ($response->rankDistribution as $rank => $studentCount) {
            $rows[] = [$rank, $studentCount];
        }

        $bytes = Services::reportsService()->renderExcel('Academic Performance', $headers, $rows);

        return $this->response->download('academic-performance.xlsx', $bytes, true);
    }

    private function requireAcademicSessionId(): int
    {
        $academicSessionId = (int) ($this->request->getGet('academic_session_id') ?? 0);

        if ($academicSessionId <= 0) {
            throw new ValidationException(['academic_session_id' => 'academic_session_id query parameter is required.']);
        }

        return $academicSessionId;
    }

    private function requireExamId(): int
    {
        $examId = (int) ($this->request->getGet('exam_id') ?? 0);

        if ($examId <= 0) {
            throw new ValidationException(['exam_id' => 'exam_id query parameter is required.']);
        }

        return $examId;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function requireDateRange(): array
    {
        $fromDate = (string) ($this->request->getGet('from_date') ?? '');
        $toDate   = (string) ($this->request->getGet('to_date') ?? '');

        $fields = [];

        if ($fromDate === '' || strtotime($fromDate) === false) {
            $fields['from_date'] = 'from_date query parameter is required and must be a valid date.';
        }

        if ($toDate === '' || strtotime($toDate) === false) {
            $fields['to_date'] = 'to_date query parameter is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$fromDate, $toDate];
    }
}
