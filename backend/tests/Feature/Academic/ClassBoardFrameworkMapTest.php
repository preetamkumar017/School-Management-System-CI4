<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class ClassBoardFrameworkMapTest extends AcademicTestCase
{
    public function testClassBoardFrameworkMapCRUD(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        
        // Retrieve / create a framework fixture reference
        $db = \Config\Database::connect();
        $frameworkId = 1;
        if ($db->tableExists('academic_frameworks')) {
            // Seed a board in geo_boards first if not exists
            if ($db->tableExists('geo_boards')) {
                $existingBoard = $db->table('geo_boards')->where('board_id', 1)->get()->getRow();
                if ($existingBoard === null) {
                    $db->table('geo_boards')->insert([
                        'board_id'   => 1,
                        'name'       => 'CBSE Board',
                        'short_name' => 'CBSE',
                        'board_type' => 'National',
                        'status'     => 'Active',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Seed a grading scheme
            $gradingSchemeId = $this->createGradingScheme('CBSE Scheme ' . uniqid('', true));

            $existing = $db->table('academic_frameworks')->get()->getRow();
            if ($existing) {
                $frameworkId = (int)$existing->framework_id;
            } else {
                $db->table('academic_frameworks')->insert([
                    'framework_id'      => 1,
                    'name'              => 'CBSE Framework',
                    'board_id'          => 1,
                    'grading_scheme_id' => $gradingSchemeId,
                    'level_divisions'   => '["Primary"]',
                    'version'           => 1,
                    'approval_status'   => 'APPROVED',
                ]);
            }
        }

        // Map Class to Board Framework
        $res = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/class-board-maps', [
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'framework_id'        => $frameworkId,
        ]);
        $res->assertStatus(201);
        $mapId = $this->decode($res)['data']['class_board_map_id'];

        // Get Mapping
        $get = $this->withHeaders($headers)->get("api/v1/academic/class-board-maps/{$mapId}");
        $get->assertStatus(200);

        // List Mappings
        $list = $this->withHeaders($headers)->get("api/v1/academic/class-board-maps?academic_session_id={$sessionId}");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);

        // Delete Mapping
        $this->withHeaders($headers)->delete("api/v1/academic/class-board-maps/{$mapId}")->assertStatus(204);
    }
}
