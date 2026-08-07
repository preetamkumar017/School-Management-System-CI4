<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Admission\Models\ApplicationModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Admission\AdmissionTestCase;

/**
 * @internal
 * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md — BR-ADM-007
 * (Provisional Seat Hold Expiry) and BR-ADM-008 (Waitlist Ranking Order),
 * exercised through the real HTTP surface
 * (`POST /admission/applications/release-expired-holds`), matching this
 * suite's existing convention (ApplicationTest, ConfirmEnrollmentTest).
 */
final class SeatHoldReleaseTest extends AdmissionTestCase
{
    public function testShortlistingSetsAHoldExpiryFromTheConfiguredPeriod(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $applicationId = $this->createApplicationFixture(null, 'VERIFIED');

        $shortlist = $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/shortlist");
        $shortlist->assertStatus(200);
        $body = $this->decode($shortlist)['data'];

        $this->assertSame('SHORTLISTED', $body['status']);
        $this->assertNotNull($body['hold_expires_at']);

        // 72 hours (ADR-016 §2's documented default), give or take a few
        // seconds of test execution time.
        $expected = Time::now()->addHours(72)->getTimestamp();
        $actual   = Time::parse($body['hold_expires_at'])->getTimestamp();
        $this->assertLessThan(30, abs($expected - $actual), 'hold_expires_at should be ~72 hours from shortlisting.');
    }

    public function testAHoldThatHasNotExpiredIsLeftUntouched(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $classId       = $this->createClassFixture();
        $applicationId = $this->shortlistApplicationFor($headers, $classId);

        $release = $this->withHeaders($headers)->post('api/v1/admission/applications/release-expired-holds');
        $release->assertStatus(200);
        $body = $this->decode($release)['data'];

        $this->assertSame(0, $body['released_count']);
        $this->assertSame([], $body['releases']);

        $application = (new ApplicationModel())->find($applicationId);
        $this->assertSame('SHORTLISTED', $application->status);
        $this->assertNotNull($application->hold_expires_at);
    }

    public function testAnExpiredHoldIsReleasedAndTheEarliestWaitlistedApplicantIsPromoted(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $classId = $this->createClassFixture();

        $shortlistedId = $this->shortlistApplicationFor($headers, $classId);
        $this->backdateHoldExpiry($shortlistedId, -1);

        $waitlistedId = $this->createApplicationFixture($classId, 'WAITLISTED');
        $this->setSubmittedAt($waitlistedId, -60);

        $release = $this->withHeaders($headers)->post('api/v1/admission/applications/release-expired-holds');
        $release->assertStatus(200);
        $body = $this->decode($release)['data'];

        $this->assertSame(1, $body['released_count']);
        $this->assertSame(1, $body['promoted_count']);
        $this->assertSame($shortlistedId, $body['releases'][0]['released_application_id']);
        $this->assertSame($waitlistedId, $body['releases'][0]['promoted_application_id']);

        $released = (new ApplicationModel())->find($shortlistedId);
        $this->assertSame('REJECTED', $released->status);
        $this->assertNull($released->hold_expires_at);
        $this->assertNotNull($released->decided_at);

        $promoted = (new ApplicationModel())->find($waitlistedId);
        $this->assertSame('SHORTLISTED', $promoted->status);
        $this->assertNotNull($promoted->hold_expires_at);
    }

    public function testExpiredHoldWithNoWaitlistedApplicantsIsReleasedButNothingIsPromoted(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $classId       = $this->createClassFixture();
        $shortlistedId = $this->shortlistApplicationFor($headers, $classId);
        $this->backdateHoldExpiry($shortlistedId, -1);

        $release = $this->withHeaders($headers)->post('api/v1/admission/applications/release-expired-holds');
        $release->assertStatus(200);
        $body = $this->decode($release)['data'];

        $this->assertSame(1, $body['released_count']);
        $this->assertSame(0, $body['promoted_count']);
        $this->assertNull($body['releases'][0]['promoted_application_id']);
    }

    /**
     * BR-ADM-008: with three waitlisted applicants for the same class, the
     * strictly-earliest-submitted one must be promoted — not merely "any"
     * of them.
     */
    public function testWaitlistPromotionRespectsStrictSubmittedAtRankOrder(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $classId = $this->createClassFixture();

        $shortlistedId = $this->shortlistApplicationFor($headers, $classId);
        $this->backdateHoldExpiry($shortlistedId, -1);

        $thirdInLine  = $this->createApplicationFixture($classId, 'WAITLISTED');
        $this->setSubmittedAt($thirdInLine, -10);

        $firstInLine = $this->createApplicationFixture($classId, 'WAITLISTED');
        $this->setSubmittedAt($firstInLine, -100);

        $secondInLine = $this->createApplicationFixture($classId, 'WAITLISTED');
        $this->setSubmittedAt($secondInLine, -50);

        $release = $this->withHeaders($headers)->post('api/v1/admission/applications/release-expired-holds');
        $release->assertStatus(200);
        $body = $this->decode($release)['data'];

        $this->assertSame($firstInLine, $body['releases'][0]['promoted_application_id']);

        $promoted = (new ApplicationModel())->find($firstInLine);
        $this->assertSame('SHORTLISTED', $promoted->status);

        $stillWaitlistedSecond = (new ApplicationModel())->find($secondInLine);
        $this->assertSame('WAITLISTED', $stillWaitlistedSecond->status);

        $stillWaitlistedThird = (new ApplicationModel())->find($thirdInLine);
        $this->assertSame('WAITLISTED', $stillWaitlistedThird->status);
    }

    public function testConfirmEnrollmentRejectsAnApplicationWhoseHoldHasAlreadyBeenReleased(): void
    {
        [$headers] = $this->authenticatedHeaders();
        $classId       = $this->createClassFixture();
        $shortlistedId = $this->shortlistApplicationFor($headers, $classId);
        $this->backdateHoldExpiry($shortlistedId, -1);

        $this->withHeaders($headers)->post('api/v1/admission/applications/release-expired-holds')
            ->assertStatus(200);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$shortlistedId}/confirm-enrollment"),
            BusinessRuleException::class,
            'APPLICATION_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    /**
     * @return array{0: array<string, string>}
     */
    private function authenticatedHeaders(): array
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        return [$this->authHeaders($tokens['access_token'])];
    }

    private function shortlistApplicationFor(array $headers, int $classId): int
    {
        $applicationId = $this->createApplicationFixture($classId, 'VERIFIED');
        $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/shortlist")
            ->assertStatus(200);

        return $applicationId;
    }

    private function backdateHoldExpiry(int $applicationId, int $minutesFromNow): void
    {
        (new ApplicationModel())->update($applicationId, [
            'hold_expires_at' => Time::now()->addMinutes($minutesFromNow)->toDateTimeString(),
        ]);
    }

    private function setSubmittedAt(int $applicationId, int $minutesFromNow): void
    {
        (new ApplicationModel())->update($applicationId, [
            'submitted_at' => Time::now()->addMinutes($minutesFromNow)->toDateTimeString(),
        ]);
    }
}
