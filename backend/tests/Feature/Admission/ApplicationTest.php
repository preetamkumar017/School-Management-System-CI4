<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\Admission\AdmissionTestCase;

/**
 * @internal
 */
final class ApplicationTest extends AdmissionTestCase
{
    public function testCreateApplicationAndReadItBack(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $classId = $this->createClassFixture();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/applications', [
            'applicant_name'   => 'Jane Doe',
            'dob'              => '2016-05-10',
            'class_applied_id' => $classId,
            'aadhaar_number'   => self::VALID_AADHAAR,
            'category'         => 'GENERAL',
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('Jane Doe', $body['applicant_name']);
        $this->assertSame('SUBMITTED', $body['status']);
        $this->assertStringStartsWith('APP-', $body['application_reference_no']);

        $show = $this->withHeaders($headers)->get('api/v1/admission/applications/' . $body['application_id']);
        $show->assertStatus(200);
        $this->assertSame('Jane Doe', $this->decode($show)['data']['applicant_name']);
    }

    public function testInvalidAadhaarChecksumIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $classId = $this->createClassFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/applications', [
                'applicant_name'   => 'Bad Aadhaar',
                'dob'              => '2016-05-10',
                'class_applied_id' => $classId,
                'aadhaar_number'   => '234567890123',
                'category'         => 'GENERAL',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    public function testApplicationRequiresAnExistingClass(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/applications', [
                'applicant_name'   => 'No Class',
                'dob'              => '2016-05-10',
                'class_applied_id' => 999999999,
                'category'         => 'GENERAL',
            ]),
            BusinessRuleException::class,
            'CLASS_NOT_FOUND',
            422,
        );
    }

    public function testFullLifecycleThroughShortlistAndWaitlist(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $applicationId = $this->createApplicationFixture();

        $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/verify")
            ->assertStatus(200);
        $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/shortlist")
            ->assertStatus(200);

        $waitlist = $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/waitlist");
        $waitlist->assertStatus(200);
        $this->assertSame('WAITLISTED', $this->decode($waitlist)['data']['status']);
    }

    public function testInvalidStatusTransitionIsRejected(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $applicationId = $this->createApplicationFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/shortlist"),
            BusinessRuleException::class,
            'APPLICATION_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    public function testRejectSetsDecidedAt(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $applicationId = $this->createApplicationFixture();

        $reject = $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/reject");
        $reject->assertStatus(200);
        $body = $this->decode($reject)['data'];
        $this->assertSame('REJECTED', $body['status']);
        $this->assertNotNull($body['decided_at']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/reject"),
            BusinessRuleException::class,
            'APPLICATION_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    public function testListApplicationsIsPaginatedAndFilterable(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $classId = $this->createClassFixture();

        $this->createApplicationFixture($classId, 'SUBMITTED');
        $this->createApplicationFixture($classId, 'VERIFIED');
        $this->createApplicationFixture($classId, 'VERIFIED');

        $list = $this->withHeaders($headers)->get("api/v1/admission/applications?status=VERIFIED&class_id={$classId}&per_page=1&page=1");
        $list->assertStatus(200);
        $body = $this->decode($list);
        $this->assertCount(1, $body['data']);
        $this->assertSame(2, $body['meta']['pagination']['total']);
        $this->assertSame(1, $body['meta']['pagination']['page']);
    }
}
