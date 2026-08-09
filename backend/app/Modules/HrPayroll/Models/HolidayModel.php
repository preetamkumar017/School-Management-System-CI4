<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;

class HolidayModel extends BaseModel
{
    protected $table          = 'school_holidays';
    protected $primaryKey     = 'holiday_id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'holiday_date',
        'name',
        'type',
        'description',
        'is_recurring',
    ];

    protected $useTimestamps = true;

    /**
     * Get all holiday dates for a given year as plain date strings.
     * @return list<string>  e.g. ['2026-08-15', '2026-10-02', ...]
     */
    public function getHolidayDatesForYear(int $year): array
    {
        $rows = $this->where("YEAR(holiday_date)", $year)->findAll();
        return array_column($rows, 'holiday_date');
    }

    /**
     * List holidays in a date range [from, to] inclusive.
     * @return list<array>
     */
    public function findInRange(string $from, string $to): array
    {
        return $this->where('holiday_date >=', $from)
                    ->where('holiday_date <=', $to)
                    ->orderBy('holiday_date', 'ASC')
                    ->findAll();
    }
}
