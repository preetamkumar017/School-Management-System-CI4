<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class SubjectTest extends AcademicTestCase
{
    public function testCreateUpdateAndReadSubject(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/subjects', [
            'subject_name' => 'Mathematics',
            'subject_code' => 'MATH' . random_int(100, 999),
        ]);
        $create->assertStatus(201);
        $subjectId = $this->decode($create)['data']['subject_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')
            ->patch('api/v1/academic/subjects/' . $subjectId, [
                'subject_name' => 'Mathematics (Advanced)',
                'subject_code' => $this->decode($create)['data']['subject_code'],
            ]);
        $update->assertStatus(200);
        $this->assertSame('Mathematics (Advanced)', $this->decode($update)['data']['subject_name']);
    }

    public function testDuplicateSubjectCodeIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $code    = 'DUP' . random_int(1000, 9999);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/subjects', [
            'subject_name' => 'Physics',
            'subject_code' => $code,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/subjects', [
                'subject_name' => 'Physics 2',
                'subject_code' => $code,
            ]),
            BusinessRuleException::class,
            'SUBJECT_CODE_ALREADY_TAKEN',
            422,
        );
    }
}
