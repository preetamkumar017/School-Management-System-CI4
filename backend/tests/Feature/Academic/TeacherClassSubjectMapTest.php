<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class TeacherClassSubjectMapTest extends AcademicTestCase
{
    public function testTeacherClassSubjectAssignment(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $subjectId = $this->createSubject();
        
        // Seed department, designation and employee
        $db = \Config\Database::connect();
        $employeeId = 1;
        if ($db->tableExists('employees')) {
            $existingDep = $db->table('departments')->where('department_id', 1)->get()->getRow();
            if ($existingDep === null) {
                $db->table('departments')->insert([
                    'department_id'   => 1,
                    'department_name' => 'Academics',
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }
            $existingDesg = $db->table('designations')->where('designation_id', 1)->get()->getRow();
            if ($existingDesg === null) {
                $db->table('designations')->insert([
                    'designation_id'   => 1,
                    'designation_name' => 'Teacher',
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
            }

            $existing = $db->table('employees')->where('employee_id', 1)->get()->getRow();
            if ($existing === null) {
                $db->table('employees')->insert([
                    'employee_id'           => 1,
                    'employee_code'         => 'EMP001',
                    'full_name'             => 'John Doe',
                    'department_id'         => 1,
                    'designation_id'        => 1,
                    'joining_date'          => '2026-04-01',
                    'salary_structure_json' => '{}',
                    'status'                => 'Active',
                ]);
            }
        }

        // Create assignment
        $res = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/teacher-maps', [
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'section_id'          => $sectionId,
            'subject_id'          => $subjectId,
            'employee_id'         => $employeeId,
        ]);
        $res->assertStatus(201);
        $assignmentId = $this->decode($res)['data']['teacher_class_subject_map_id'];

        // Get Assignment
        $get = $this->withHeaders($headers)->get("api/v1/academic/teacher-maps/{$assignmentId}");
        $get->assertStatus(200);

        // List assignments
        $list = $this->withHeaders($headers)->get("api/v1/academic/teacher-maps?academic_session_id={$sessionId}");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);

        // Delete Assignment
        $this->withHeaders($headers)->delete("api/v1/academic/teacher-maps/{$assignmentId}")->assertStatus(204);
    }
}
