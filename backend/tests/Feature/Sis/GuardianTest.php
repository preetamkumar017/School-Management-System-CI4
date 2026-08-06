<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\ValidationException;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 */
final class GuardianTest extends SisTestCase
{
    public function testCreateUpdateAndReadGuardian(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/guardians', [
            'full_name'     => 'John Guardian',
            'relationship'  => 'FATHER',
            'mobile_number' => '9876543210',
            'email'         => 'john@example.com',
        ]);
        $create->assertStatus(201);
        $guardianId = $this->decode($create)['data']['guardian_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch('api/v1/sis/guardians/' . $guardianId, [
            'full_name'     => 'John Guardian Updated',
            'relationship'  => 'FATHER',
            'mobile_number' => '9876543210',
            'email'         => 'john@example.com',
        ]);
        $update->assertStatus(200);
        $this->assertSame('John Guardian Updated', $this->decode($update)['data']['full_name']);

        $show = $this->withHeaders($headers)->get('api/v1/sis/guardians/' . $guardianId);
        $show->assertStatus(200);
        $this->assertSame('John Guardian Updated', $this->decode($show)['data']['full_name']);
    }

    public function testInvalidMobileNumberIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/sis/guardians', [
                'full_name'     => 'Bad Number',
                'relationship'  => 'MOTHER',
                'mobile_number' => '12345',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }
}
