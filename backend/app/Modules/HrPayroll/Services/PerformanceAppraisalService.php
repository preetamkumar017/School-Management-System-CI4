<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\AppraisalCycleModel;
use App\Modules\HrPayroll\Models\AppraisalModel;
use App\Modules\HrPayroll\Models\AppraisalKpiModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use App\Core\Authz\ModuleAuthorizer;

class PerformanceAppraisalService
{
    public function __construct(
        private readonly AppraisalCycleModel $cycleModel,
        private readonly AppraisalModel $appraisalModel,
        private readonly AppraisalKpiModel $kpiModel,
        private readonly EmployeeModel $employeeModel,
        private readonly ModuleAuthorizer $moduleAuthorizer
    ) {
    }

    public function getCycles(): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');
        return $this->cycleModel->orderBy('created_at', 'DESC')->findAll();
    }

    public function createCycle(array $data): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $cycleId = $this->cycleModel->insert([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'Active',
            ]);

            // Get all active employees to generate appraisals
            $employees = $this->employeeModel->where('status', 'Active')->findAll();

            $kpis = [
                ['name' => 'Student Results', 'weightage' => 40],
                ['name' => 'Classroom Observation', 'weightage' => 40],
                ['name' => 'Behavior & Discipline', 'weightage' => 20],
            ];

            foreach ($employees as $emp) {
                $appraisalId = $this->appraisalModel->insert([
                    'cycle_id' => $cycleId,
                    'employee_id' => $emp->employee_id,
                    'status' => 'Self Appraisal Pending',
                    'recommendation' => 'None',
                ]);

                foreach ($kpis as $kpi) {
                    $this->kpiModel->insert([
                        'appraisal_id' => $appraisalId,
                        'kpi_name' => $kpi['name'],
                        'weightage' => $kpi['weightage'],
                    ]);
                }
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new DatabaseException("Failed to create cycle");
            }

            return $this->cycleModel->find($cycleId)->toArray();
        } catch (\Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function getAppraisalsByCycle(int $cycleId): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $appraisals = $this->appraisalModel->where('cycle_id', $cycleId)->findAll();
        $employees = $this->employeeModel->findAll();
        $empMap = [];
        foreach ($employees as $e) {
            $empMap[$e->employee_id] = $e;
        }

        $result = [];
        foreach ($appraisals as $app) {
            $emp = $empMap[$app->employee_id] ?? null;
            $result[] = [
                'appraisal_id' => $app->appraisal_id,
                'employee_name' => $emp ? $emp->first_name . ' ' . $emp->last_name : 'Unknown',
                'department' => $emp ? $emp->department : '-',
                'status' => $app->status,
                'final_rating' => $app->final_rating,
                'recommendation' => $app->recommendation,
            ];
        }

        return $result;
    }

    public function getAppraisalDetails(int $appraisalId): array
    {
        // TODO: Enforce ownership OR manage
        $appraisal = $this->appraisalModel->find($appraisalId);
        $kpis = $this->kpiModel->where('appraisal_id', $appraisalId)->findAll();
        $emp = $this->employeeModel->find($appraisal->employee_id);

        return [
            'appraisal' => $appraisal,
            'kpis' => $kpis,
            'employee' => $emp,
        ];
    }

    public function submitSelfAppraisal(int $appraisalId, array $kpiScores): void
    {
        // TODO: Enforce ownership
        $db = \Config\Database::connect();
        $db->transStart();

        $totalScore = 0;
        foreach ($kpiScores as $kpiId => $data) {
            $this->kpiModel->update($kpiId, [
                'self_score' => $data['score'],
                'self_comments' => $data['comments'] ?? null,
            ]);
            
            $kpi = $this->kpiModel->find($kpiId);
            $totalScore += ($data['score'] * ($kpi->weightage / 100));
        }

        $this->appraisalModel->update($appraisalId, [
            'self_rating' => round($totalScore, 2),
            'status' => 'Review Pending',
        ]);

        $db->transComplete();
    }

    public function submitManagerReview(int $appraisalId, array $kpiScores, string $comments, string $recommendation): void
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $db = \Config\Database::connect();
        $db->transStart();

        $totalScore = 0;
        foreach ($kpiScores as $kpiId => $data) {
            $this->kpiModel->update($kpiId, [
                'evaluator_score' => $data['score'],
            ]);
            
            $kpi = $this->kpiModel->find($kpiId);
            $totalScore += ($data['score'] * ($kpi->weightage / 100));
        }

        $finalRating = round($totalScore, 2);

        $this->appraisalModel->update($appraisalId, [
            'evaluator_rating' => $finalRating,
            'final_rating' => $finalRating,
            'evaluator_comments' => $comments,
            'recommendation' => $recommendation,
            'status' => 'Completed',
        ]);

        $db->transComplete();
    }
}
