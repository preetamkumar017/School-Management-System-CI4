<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class SectionTest extends AcademicTestCase
{
    public function testCreateUpdateAndListSectionsByClass(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $classId = $this->createClassFixture();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sections', [
            'class_id'     => $classId,
            'section_name' => 'A',
            'capacity'     => 40,
        ]);
        $create->assertStatus(201);
        $sectionId = $this->decode($create)['data']['section_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')
            ->patch('api/v1/academic/sections/' . $sectionId, ['section_name' => 'A', 'capacity' => 45]);
        $update->assertStatus(200);
        $this->assertSame(45, $this->decode($update)['data']['capacity']);

        $list = $this->withHeaders($headers)->get('api/v1/academic/sections?class_id=' . $classId);
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);
    }

    public function testSectionRequiresAnExistingClass(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sections', [
                'class_id'     => 999999999,
                'section_name' => 'A',
                'capacity'     => 40,
            ]),
            BusinessRuleException::class,
            'CLASS_NOT_FOUND',
            422,
        );
    }

    public function testDuplicateSectionNameWithinClassIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $classId = $this->createClassFixture();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sections', [
            'class_id'     => $classId,
            'section_name' => 'B',
            'capacity'     => 40,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sections', [
                'class_id'     => $classId,
                'section_name' => 'B',
                'capacity'     => 30,
            ]),
            BusinessRuleException::class,
            'SECTION_NAME_ALREADY_TAKEN_IN_CLASS',
            422,
        );
    }
}
