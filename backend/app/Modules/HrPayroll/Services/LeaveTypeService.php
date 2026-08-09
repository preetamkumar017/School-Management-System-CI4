<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\LeaveTypeModel;

class LeaveTypeService
{
    public function __construct(private readonly LeaveTypeModel $model) {}

    /** @return list<array> */
    public function listAll(bool $activeOnly = false): array
    {
        if ($activeOnly) {
            return $this->model->findActive();
        }
        return $this->model->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    public function find(int $id): ?array
    {
        $row = $this->model->find($id);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        return $this->model->findByCode($code);
    }

    public function create(array $data): int
    {
        $data['code'] = strtoupper(trim($data['code']));
        return (int) $this->model->insert($data, true);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    /**
     * Resolve sandwich_rule for a given leave type code.
     * If the leave type has an explicit sandwich_rule (0 or 1), use it.
     * Otherwise fall back to the $globalSandwichRule.
     */
    public function resolveSandwichRule(string $code, bool $globalSandwichRule): bool
    {
        $leaveType = $this->model->findByCode($code);
        if ($leaveType !== null && $leaveType['sandwich_rule'] !== null) {
            return (bool) $leaveType['sandwich_rule'];
        }
        return $globalSandwichRule;
    }
}
