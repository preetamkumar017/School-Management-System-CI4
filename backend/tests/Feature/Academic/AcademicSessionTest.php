<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class AcademicSessionTest extends AcademicTestCase
{
    public function testCreateSessionAndReadItBack(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sessions', [
            'session_name' => '2026-27',
            'start_date'   => '2026-04-01',
            'end_date'     => '2027-03-31',
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('2026-27', $body['session_name']);
        $this->assertSame('PLANNED', $body['status']);

        $show = $this->withHeaders($headers)->get('api/v1/academic/sessions/' . $body['academic_session_id']);
        $show->assertStatus(200);
        $this->assertSame('2026-27', $this->decode($show)['data']['session_name']);
    }

    public function testDuplicateSessionNameIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sessions', [
            'session_name' => '2030-31',
            'start_date'   => '2030-04-01',
            'end_date'     => '2031-03-31',
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sessions', [
                'session_name' => '2030-31',
                'start_date'   => '2035-04-01',
                'end_date'     => '2036-03-31',
            ]),
            BusinessRuleException::class,
            'ACADEMIC_SESSION_NAME_ALREADY_TAKEN',
            422,
        );
    }

    public function testOverlappingDateRangeIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sessions', [
            'session_name' => '2040-41',
            'start_date'   => '2040-04-01',
            'end_date'     => '2041-03-31',
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/sessions', [
                'session_name' => '2040-42',
                'start_date'   => '2040-06-01',
                'end_date'     => '2041-06-01',
            ]),
            BusinessRuleException::class,
            'ACADEMIC_SESSION_DATE_RANGE_OVERLAPS',
            422,
        );
    }

    public function testStatusTransitionIsForwardOnly(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession('2050-51', '2050-04-01', '2051-03-31');

        $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/academic/sessions/{$sessionId}/status", ['status' => 'ACTIVE'])
            ->assertStatus(200);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->post("api/v1/academic/sessions/{$sessionId}/status", ['status' => 'PLANNED']),
            BusinessRuleException::class,
            'ACADEMIC_SESSION_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    public function testGetCurrentActiveSession(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession('2060-61', '2060-04-01', '2061-03-31');

        $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/academic/sessions/{$sessionId}/status", ['status' => 'ACTIVE'])
            ->assertStatus(200);

        $current = $this->withHeaders($headers)->get('api/v1/academic/sessions/current');
        $current->assertStatus(200);
        $this->assertSame($sessionId, $this->decode($current)['data']['academic_session_id']);
    }
}
