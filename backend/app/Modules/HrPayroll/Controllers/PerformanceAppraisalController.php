<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Modules\HrPayroll\Services\PerformanceAppraisalService;
use App\Modules\HrPayroll\Models\AppraisalCycleModel;
use App\Modules\HrPayroll\Models\AppraisalModel;
use App\Modules\HrPayroll\Models\AppraisalKpiModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Core\Authz\ModuleAuthorizer;
use App\Modules\Administration\Models\UserModel;

class PerformanceAppraisalController extends BaseController
{
    private PerformanceAppraisalService $service;

    public function __construct()
    {
        $this->service = new PerformanceAppraisalService(
            new AppraisalCycleModel(),
            new AppraisalModel(),
            new AppraisalKpiModel(),
            new EmployeeModel(),
            new ModuleAuthorizer(new UserModel())
        );
    }

    public function getCycles()
    {
        return $this->respondSuccess($this->service->getCycles());
    }

    public function createCycle()
    {
        $data = $this->request->getJSON(true);
        $cycle = $this->service->createCycle($data);
        return $this->respondSuccess($cycle, 201);
    }

    public function getAppraisals(int $cycleId)
    {
        return $this->respondSuccess($this->service->getAppraisalsByCycle($cycleId));
    }

    public function show(int $appraisalId)
    {
        return $this->respondSuccess($this->service->getAppraisalDetails($appraisalId));
    }

    public function submitSelf(int $appraisalId)
    {
        $data = $this->request->getJSON(true);
        $this->service->submitSelfAppraisal($appraisalId, $data['kpi_scores']);
        return $this->respondSuccess(['message' => 'Self appraisal submitted']);
    }

    public function submitManager(int $appraisalId)
    {
        $data = $this->request->getJSON(true);
        $this->service->submitManagerReview($appraisalId, $data['kpi_scores'], $data['comments'] ?? '', $data['recommendation'] ?? 'None');
        return $this->respondSuccess(['message' => 'Manager review submitted']);
    }
}
