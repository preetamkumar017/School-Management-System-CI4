<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class StaffAttendanceSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        
        // Find active employees
        $employees = $db->table('employees')
            ->where('status', 'Active')
            ->get()
            ->getResult();

        $records = [];
        $date = '2026-08-09';
        
        foreach ($employees as $emp) {
            $stateOptions = ['Present', 'Present', 'Present', 'Present', 'On Leave', 'Missing Punch', 'Half Day'];
            $state = $stateOptions[array_rand($stateOptions)];

            $firstIn = null;
            $lastOut = null;
            $overtimeHrs = 0;
            $lateMins = 0;
            $earlyMins = 0;
            $isHalfDay = 0;
            $totalHrs = 0;

            if ($state !== 'On Leave') {
                $baseInTime = strtotime("$date 09:00:00");
                $baseOutTime = strtotime("$date 17:00:00");

                if ($state === 'Missing Punch') {
                    $firstIn = date('Y-m-d H:i:s', $baseInTime);
                    // No out time
                } elseif ($state === 'Half Day') {
                    $firstIn = date('Y-m-d H:i:s', $baseInTime);
                    $lastOut = date('Y-m-d H:i:s', strtotime("$date 13:00:00"));
                    $isHalfDay = 1;
                    $totalHrs = 4;
                } else {
                    // Present
                    $lateMins = rand(0, 10) > 8 ? rand(15, 60) : 0;
                    $firstIn = date('Y-m-d H:i:s', $baseInTime + ($lateMins * 60));
                    
                    if (rand(1, 10) > 8) {
                        $overtimeHrs = rand(1, 3);
                        $lastOut = date('Y-m-d H:i:s', $baseOutTime + ($overtimeHrs * 3600));
                    } else {
                        $lastOut = date('Y-m-d H:i:s', $baseOutTime);
                    }
                    $totalHrs = round((strtotime($lastOut) - strtotime($firstIn)) / 3600, 2);
                }
            }

            $records[] = [
                'employee_id' => $emp->employee_id,
                'attendance_date' => $date,
                'state' => $state,
                'first_in_time' => $firstIn,
                'last_out_time' => $lastOut,
                'total_hours' => $totalHrs,
                'late_minutes' => $lateMins,
                'early_minutes' => $earlyMins,
                'overtime_hours' => $overtimeHrs,
                'is_half_day' => $isHalfDay,
                'created_by' => 1,
                'updated_by' => 1,
            ];
        }

        if (!empty($records)) {
            // Clear existing for this date to avoid duplicates if run multiple times
            $db->table('staff_attendance_records')->where('attendance_date', $date)->delete();
            $db->table('staff_attendance_records')->insertBatch($records);
        }
    }
}
