<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Modules\HrPayroll\Services\HolidayService;
use CodeIgniter\HTTP\ResponseInterface;

class HolidayController extends BaseController
{
    private HolidayService $service;

    public function __construct()
    {
        $this->service = \Config\Services::holidayService();
    }

    /** GET /api/v1/hr-payroll/holidays?year=2026 */
    public function index(): ResponseInterface
    {
        $year = (int) ($this->request->getGet('year') ?? date('Y'));
        $holidays = $this->service->listForYear($year);
        return $this->respondSuccess($holidays);
    }

    /** GET /api/v1/hr-payroll/holidays/:id */
    public function show(int $id): ResponseInterface
    {
        $holiday = $this->service->find($id);
        if ($holiday === null) {
            return $this->respondError('not_found', 'HOLIDAY_NOT_FOUND', 'Holiday not found', 404);
        }
        return $this->respondSuccess($holiday);
    }

    /** POST /api/v1/hr-payroll/holidays */
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'holiday_date' => 'required|valid_date[Y-m-d]',
            'name'         => 'required|min_length[2]|max_length[100]',
            'type'         => 'required|in_list[Gazetted,Restricted,School]',
            'description'  => 'permit_empty|max_length[255]',
            'is_recurring' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData((array) $data, $rules)) {
            return $this->respondError('validation', 'VALIDATION_FAILED', 'Validation failed', 422, $this->validator->getErrors());
        }

        $id = $this->service->create([
            'holiday_date' => $data['holiday_date'],
            'name'         => $data['name'],
            'type'         => $data['type'],
            'description'  => $data['description'] ?? null,
            'is_recurring' => (int) ($data['is_recurring'] ?? 0),
        ]);

        return $this->respondCreated(['holiday_id' => $id]);
    }

    /** PATCH /api/v1/hr-payroll/holidays/:id */
    public function update(int $id): ResponseInterface
    {
        $holiday = $this->service->find($id);
        if ($holiday === null) {
            return $this->respondError('not_found', 'HOLIDAY_NOT_FOUND', 'Holiday not found', 404);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'holiday_date' => 'permit_empty|valid_date[Y-m-d]',
            'name'         => 'permit_empty|min_length[2]|max_length[100]',
            'type'         => 'permit_empty|in_list[Gazetted,Restricted,School]',
            'description'  => 'permit_empty|max_length[255]',
            'is_recurring' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData((array) $data, $rules)) {
            return $this->respondError('validation', 'VALIDATION_FAILED', 'Validation failed', 422, $this->validator->getErrors());
        }

        $this->service->update($id, (array) $data);
        return $this->respondSuccess($this->service->find($id));
    }

    /** DELETE /api/v1/hr-payroll/holidays/:id */
    public function destroy(int $id): ResponseInterface
    {
        $holiday = $this->service->find($id);
        if ($holiday === null) {
            return $this->respondError('not_found', 'HOLIDAY_NOT_FOUND', 'Holiday not found', 404);
        }
        $this->service->delete($id);
        return $this->respondSuccess(['message' => 'Holiday deleted']);
    }
}
