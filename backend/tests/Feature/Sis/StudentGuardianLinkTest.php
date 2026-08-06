<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 */
final class StudentGuardianLinkTest extends SisTestCase
{
    public function testLinkListAndUnlinkGuardian(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $studentId  = $this->createStudentFixture();
        $guardianId = $this->createGuardianFixture();

        $link = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/student-guardian-links', [
            'student_id'  => $studentId,
            'guardian_id' => $guardianId,
        ]);
        $link->assertStatus(201);

        $list = $this->withHeaders($headers)->get("api/v1/sis/student-guardian-links/by-student/{$studentId}");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);

        $this->withHeaders($headers)->delete("api/v1/sis/student-guardian-links/{$studentId}/{$guardianId}")
            ->assertStatus(200);

        $listAfter = $this->withHeaders($headers)->get("api/v1/sis/student-guardian-links/by-student/{$studentId}");
        $this->assertCount(0, $this->decode($listAfter)['data']);
    }

    public function testDuplicateLinkIsRejected(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $studentId  = $this->createStudentFixture();
        $guardianId = $this->createGuardianFixture();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/student-guardian-links', [
            'student_id'  => $studentId,
            'guardian_id' => $guardianId,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/student-guardian-links', [
                'student_id'  => $studentId,
                'guardian_id' => $guardianId,
            ]),
            BusinessRuleException::class,
            'STUDENT_GUARDIAN_LINK_ALREADY_EXISTS',
            422,
        );
    }
}
