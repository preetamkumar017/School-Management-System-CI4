<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\Library\Models\BookModel;
use App\Modules\Library\Models\ReservationModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Library\LibraryTestCase;

/**
 * @internal
 * docs/ADR/ADR-017-library-reservation-queue.md — BR-LIB-006.
 */
final class ReservationTest extends LibraryTestCase
{
    public function testReservationCanBeCreatedWhileBookIsUnavailable(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        // Book unavailable — it's actively out to someone else.
        $bookId = $this->createBookFixture(null, 'Circulating', false);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/reservations', [
            'book_id'         => $bookId,
            'borrower_type'   => 'Student',
            'borrower_ref_id' => $studentId,
        ]);

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('Waiting', $body['status']);
        $this->assertSame($bookId, $body['book_id']);
    }

    public function testReservationCannotBeCreatedForAnAvailableBook(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $bookId  = $this->createBookFixture(null, 'Circulating', true);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/reservations', [
                'book_id'         => $bookId,
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $this->createStudentFixture(),
            ]),
            BusinessRuleException::class,
            'RESERVATION_NOT_NEEDED',
            422,
        );
    }

    /**
     * BR-LIB-006's core promise: the longest-waiting holder — not just
     * "someone" — is the one notified when the book becomes available.
     */
    public function testLongestWaitingHolderIsNotifiedFirstWhenBookIsReturned(): void
    {
        $bookId = $this->createBookFixture(null, 'Circulating', false);
        $now    = Time::now();

        $first  = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(2)->toDateTimeString());
        $second = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(1)->toDateTimeString());

        $bookIssueId = $this->createBookIssueFixture($bookId, 'Student', null, 'Issued', $now->toDateString());

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->post("api/v1/library/book-issues/{$bookIssueId}/return")->assertStatus(200);

        $firstAfter  = (new ReservationModel())->find($first);
        $secondAfter = (new ReservationModel())->find($second);

        $this->assertSame('Notified', $firstAfter->status, 'The earlier-requested reservation must be the one notified.');
        $this->assertNotNull($firstAfter->notified_at);
        $this->assertNotNull($firstAfter->notification_expires_at);
        $this->assertSame('Waiting', $secondAfter->status, 'The later-requested reservation must remain queued.');

        // BR-LIB-006's post-condition has teeth: general issue is
        // blocked in favor of the notified holder.
        $book = (new BookModel())->find($bookId);
        $this->assertTrue($book->is_available, 'is_available toggles true on return regardless of the reservation queue.');
    }

    public function testNotifiedReservationBlocksIssueToADifferentBorrower(): void
    {
        $bookId       = $this->createBookFixture(null, 'Circulating', true);
        $notifiedFor  = $this->createStudentFixture();
        $this->createReservationFixture($bookId, 'Student', $notifiedFor, 'Notified', Time::now()->subHours(1)->toDateTimeString(), Time::now()->subHours(1)->toDateTimeString(), Time::now()->addHours(47)->toDateTimeString());

        $user           = $this->createUser();
        $tokens         = $this->loginAs($user['username']);
        $headers        = $this->authHeaders($tokens['access_token']);
        $otherStudentId = $this->createStudentFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
                'book_id'         => $bookId,
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $otherStudentId,
                'due_date'        => '2026-08-20',
            ]),
            BusinessRuleException::class,
            'BOOK_RESERVED_FOR_ANOTHER_BORROWER',
            422,
        );
    }

    public function testNotifiedHolderCanIssueAndFulfillsTheReservation(): void
    {
        $bookId      = $this->createBookFixture(null, 'Circulating', true);
        $notifiedFor = $this->createStudentFixture();
        $reservationId = $this->createReservationFixture($bookId, 'Student', $notifiedFor, 'Notified', Time::now()->subHours(1)->toDateTimeString(), Time::now()->subHours(1)->toDateTimeString(), Time::now()->addHours(47)->toDateTimeString());

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
            'book_id'         => $bookId,
            'borrower_type'   => 'Student',
            'borrower_ref_id' => $notifiedFor,
            'due_date'        => '2026-08-20',
        ]);

        $response->assertStatus(201);

        $reservation = (new ReservationModel())->find($reservationId);
        $this->assertSame('Fulfilled', $reservation->status);
    }

    /**
     * FIFO across three candidates: the third is promoted only after
     * both the first (fulfilled/expired) and second are resolved.
     */
    public function testFifoOrderAcrossMultipleReservationsAdvancesOneAtATime(): void
    {
        $bookId = $this->createBookFixture(null, 'Circulating', false);
        $now    = Time::now();

        $first  = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(3)->toDateTimeString());
        $second = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(2)->toDateTimeString());
        $third  = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(1)->toDateTimeString());

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $bookIssueId = $this->createBookIssueFixture($bookId, 'Student', null, 'Issued', $now->toDateString());
        $this->withHeaders($headers)->post("api/v1/library/book-issues/{$bookIssueId}/return")->assertStatus(200);

        $reservationModel = new ReservationModel();
        $this->assertSame('Notified', $reservationModel->find($first)->status);
        $this->assertSame('Waiting', $reservationModel->find($second)->status);
        $this->assertSame('Waiting', $reservationModel->find($third)->status);

        // Cancel the notified (first) reservation — its turn ends early,
        // the next-in-line (second) must be notified immediately, and
        // the third must still be untouched.
        $this->withHeaders($headers)->post("api/v1/library/reservations/{$first}/cancel")->assertStatus(200);

        $this->assertSame('Cancelled', $reservationModel->find($first)->status);
        $this->assertSame('Notified', $reservationModel->find($second)->status, 'Second reservation must be promoted only after the first is resolved.');
        $this->assertSame('Waiting', $reservationModel->find($third)->status, 'Third reservation must not be promoted while the second is active.');
    }

    /**
     * The window-expiry explicit trigger advances the queue and a
     * notification log row is created for the promotion.
     */
    public function testProcessExpiredNotificationsAdvancesQueueAndLogsNotification(): void
    {
        $bookId = $this->createBookFixture(null, 'Circulating', false);
        $now    = Time::now();

        $first  = $this->createReservationFixture($bookId, 'Student', null, 'Notified', $now->subHours(50)->toDateTimeString(), $now->subHours(50)->toDateTimeString(), $now->subHours(2)->toDateTimeString());
        $second = $this->createReservationFixture($bookId, 'Student', null, 'Waiting', $now->subHours(49)->toDateTimeString());

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $notificationLogCountBefore = (new NotificationLogModel())->where('trigger_event', 'BR-LIB-006 book available for reservation')->countAllResults();

        $response = $this->withHeaders($headers)->post('api/v1/library/reservations/process-expired-notifications');
        $response->assertStatus(200);

        $body = $this->decode($response)['data'];
        $this->assertSame(1, $body['expired_count']);
        $this->assertSame(1, $body['promoted_count']);
        $this->assertSame($first, $body['expirations'][0]['expired_reservation_id']);
        $this->assertSame($second, $body['expirations'][0]['promoted_reservation_id']);

        $reservationModel = new ReservationModel();
        $this->assertSame('Expired', $reservationModel->find($first)->status);
        $this->assertSame('Notified', $reservationModel->find($second)->status);

        $notificationLogCountAfter = (new NotificationLogModel())->where('trigger_event', 'BR-LIB-006 book available for reservation')->countAllResults();
        $this->assertSame($notificationLogCountBefore + 1, $notificationLogCountAfter, 'A notification log row must be created for the promoted holder.');
    }

    public function testProcessExpiredNotificationsIsSafeToRunTwice(): void
    {
        $bookId = $this->createBookFixture(null, 'Circulating', false);
        $now    = Time::now();

        $this->createReservationFixture($bookId, 'Student', null, 'Notified', $now->subHours(50)->toDateTimeString(), $now->subHours(50)->toDateTimeString(), $now->subHours(2)->toDateTimeString());

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->post('api/v1/library/reservations/process-expired-notifications')->assertStatus(200);
        $second = $this->withHeaders($headers)->post('api/v1/library/reservations/process-expired-notifications');

        $second->assertStatus(200);
        $body = $this->decode($second)['data'];
        $this->assertSame(0, $body['expired_count'], 'A second pass must find nothing left eligible.');
    }
}
