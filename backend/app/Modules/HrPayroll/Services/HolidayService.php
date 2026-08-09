<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\HolidayModel;

class HolidayService
{
    public function __construct(private readonly HolidayModel $model) {}

    public function listAll(?string $type = null): array
    {
        $query = $this->model->orderBy('holiday_date', 'ASC');
        if ($type !== null) {
            $query->where('type', $type);
        }
        return $query->findAll();
    }

    public function listForYear(int $year): array
    {
        return $this->model
            ->where("YEAR(holiday_date)", $year)
            ->orderBy('holiday_date', 'ASC')
            ->findAll();
    }

    public function create(array $data): int
    {
        $id = $this->model->insert($data, true);
        return (int) $id;
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    public function find(int $id): ?array
    {
        $row = $this->model->find($id);
        return $row ?: null;
    }
}
