<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class ClassSubjectMapTest extends AcademicTestCase
{
    public function testMapListAndUnmapSubjectForClass(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $subjectId = $this->createSubject();

        $map = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/class-subject-map', [
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'subject_id'          => $subjectId,
            'is_mandatory'        => 1,
        ]);
        $map->assertStatus(201);

        $list = $this->withHeaders($headers)->get("api/v1/academic/class-subject-map/by-class/{$sessionId}/{$classId}");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);
        $this->assertSame($subjectId, $this->decode($list)['data'][0]['subject_id']);

        $listMappings = $this->withHeaders($headers)->get("api/v1/academic/class-subject-map/list/{$sessionId}/{$classId}");
        $listMappings->assertStatus(200);

        $this->withHeaders($headers)->delete("api/v1/academic/class-subject-map/{$sessionId}/{$classId}/{$subjectId}")
            ->assertStatus(200);

        $listAfter = $this->withHeaders($headers)->get("api/v1/academic/class-subject-map/by-class/{$sessionId}/{$classId}");
        $this->assertCount(0, $this->decode($listAfter)['data']);
    }

    public function testDuplicateMappingIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $subjectId = $this->createSubject();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/class-subject-map', [
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'subject_id'          => $subjectId,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/class-subject-map', [
                'academic_session_id' => $sessionId,
                'class_id'            => $classId,
                'subject_id'          => $subjectId,
            ]),
            BusinessRuleException::class,
            'CLASS_SUBJECT_MAPPING_ALREADY_EXISTS',
            422,
        );
    }

    public function testUnmappingANonExistentMappingIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $subjectId = $this->createSubject();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->delete("api/v1/academic/class-subject-map/{$sessionId}/{$classId}/{$subjectId}"),
            BusinessRuleException::class,
            'CLASS_SUBJECT_MAPPING_NOT_FOUND',
            422,
        );
    }
}
