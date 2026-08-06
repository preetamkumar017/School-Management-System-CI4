<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 */
final class StudentTest extends SisTestCase
{
    public function testUpdateStudent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/sis/students/{$studentId}", [
            'full_name' => 'Updated Name',
            'dob'       => '2015-01-01',
            'category'  => 'GENERAL',
        ]);
        $update->assertStatus(200);
        $this->assertSame('Updated Name', $this->decode($update)['data']['full_name']);
    }

    public function testSectionTransferValidatesCapacity(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId, 'A', 1);

        // Fill the section's only seat with an ACTIVE student.
        $this->createStudentFixture(null, $sectionId, 'ACTIVE');

        $studentId = $this->createStudentFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->post("api/v1/sis/students/{$studentId}/section-transfer", ['new_section_id' => $sectionId]),
            BusinessRuleException::class,
            'SECTION_CAPACITY_EXCEEDED',
            422,
        );
    }

    public function testSectionTransferRequiresAnExistingSection(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->post("api/v1/sis/students/{$studentId}/section-transfer", ['new_section_id' => 999999999]),
            BusinessRuleException::class,
            'SECTION_NOT_FOUND',
            422,
        );
    }

    public function testActivationRequiresASectionAssigned(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture(null, null, 'DRAFT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/status", ['status' => 'ACTIVE']),
            BusinessRuleException::class,
            'STUDENT_PROFILE_INCOMPLETE',
            422,
        );
    }

    public function testActivationRequiresAtLeastOneLinkedGuardian(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sectionId = $this->createSection();
        $studentId = $this->createStudentFixture(null, $sectionId, 'DRAFT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/status", ['status' => 'ACTIVE']),
            BusinessRuleException::class,
            'STUDENT_NO_GUARDIAN_LINKED',
            422,
        );
    }

    public function testActivationSucceedsOnceCompleteAndGuardianLinked(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $sectionId  = $this->createSection();
        $studentId  = $this->createStudentFixture(null, $sectionId, 'DRAFT');
        $guardianId = $this->createGuardianFixture();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/student-guardian-links', [
            'student_id'  => $studentId,
            'guardian_id' => $guardianId,
        ])->assertStatus(201);

        $activate = $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/status", ['status' => 'ACTIVE']);
        $activate->assertStatus(200);
        $this->assertSame('ACTIVE', $this->decode($activate)['data']['status']);
    }

    public function testStatusTransitionIsForwardOnly(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture(null, null, 'PROMOTED');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/status", ['status' => 'DRAFT']),
            BusinessRuleException::class,
            'STUDENT_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    public function testListStudentsBySection(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sectionId = $this->createSection();

        $this->createStudentFixture(null, $sectionId);
        $this->createStudentFixture(null, $sectionId);

        $list = $this->withHeaders($headers)->get("api/v1/sis/students?section_id={$sectionId}");
        $list->assertStatus(200);
        $this->assertCount(2, $this->decode($list)['data']);
    }
}
