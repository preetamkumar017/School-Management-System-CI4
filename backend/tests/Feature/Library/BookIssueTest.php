<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\ConfigurationModel;
use App\Modules\Library\Models\BookIssueModel;
use App\Modules\Library\Models\BookModel;
use Tests\Support\Library\LibraryTestCase;

/**
 * @internal
 */
final class BookIssueTest extends LibraryTestCase
{
    public function testIssueBookSucceedsAndMarksBookUnavailable(): void
    {
        $user     = $this->createUser();
        $tokens   = $this->loginAs($user['username']);
        $headers  = $this->authHeaders($tokens['access_token']);
        $bookId   = $this->createBookFixture();
        $studentId = $this->createStudentFixture();

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
            'book_id'         => $bookId,
            'borrower_type'   => 'Student',
            'borrower_ref_id' => $studentId,
            'due_date'        => '2026-08-20',
        ]);

        $response->assertStatus(201);
        $this->assertSame('Issued', $this->decode($response)['data']['status']);

        $book = (new BookModel())->find($bookId);
        $this->assertFalse($book->is_available);
    }

    /**
     * BR-LIB-004: a Reference book can never be issued.
     */
    public function testReferenceBookCannotBeIssued(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $bookId  = $this->createBookFixture(null, 'Reference');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
                'book_id'         => $bookId,
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $this->createStudentFixture(),
                'due_date'        => '2026-08-20',
            ]),
            BusinessRuleException::class,
            'BOOK_NOT_CIRCULATING',
            422,
        );
    }

    /**
     * BR-LIB-001: decided default of 3 books per borrower (ADR-009 §2).
     */
    public function testMaxBooksLimitIsEnforced(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        for ($i = 0; $i < 3; $i++) {
            $this->createBookIssueFixture(null, 'Student', $studentId);
        }

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
                'book_id'         => $this->createBookFixture(),
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $studentId,
                'due_date'        => '2026-08-20',
            ]),
            BusinessRuleException::class,
            'MAX_BOOKS_LIMIT_REACHED',
            422,
        );
    }

    /**
     * ADR-011 §4: the limit reads live from Configuration, not a
     * hardcoded constant — proven by changing the seeded value and
     * observing the enforced ceiling change with it.
     */
    public function testMaxBooksLimitReadsLiveFromConfiguration(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $configModel = new ConfigurationModel();
        $configModel->update(
            $configModel->findByKey('library.max_books_per_borrower')->setting_id,
            ['setting_value' => '1'],
        );

        $this->createBookIssueFixture(null, 'Student', $studentId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
                'book_id'         => $this->createBookFixture(),
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $studentId,
                'due_date'        => '2026-08-20',
            ]),
            BusinessRuleException::class,
            'MAX_BOOKS_LIMIT_REACHED',
            422,
        );
    }

    /**
     * BR-LIB-005: decided threshold of ₹0 — any unpaid fine blocks issue (ADR-009 §6).
     */
    public function testOutstandingFineBlocksIssue(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $this->createBookIssueFixture(null, 'Student', $studentId, 'Returned', '2026-08-01', 10.0, false);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
                'book_id'         => $this->createBookFixture(),
                'borrower_type'   => 'Student',
                'borrower_ref_id' => $studentId,
                'due_date'        => '2026-08-20',
            ]),
            BusinessRuleException::class,
            'OUTSTANDING_FINE_BLOCKS_ISSUE',
            422,
        );
    }

    /**
     * BR-LIB-002: fine = days overdue x decided ₹2/day (ADR-009 §3).
     */
    public function testReturnCalculatesOverdueFineCorrectly(): void
    {
        $user        = $this->createUser();
        $tokens      = $this->loginAs($user['username']);
        $headers     = $this->authHeaders($tokens['access_token']);
        $pastDueDate = (new \DateTimeImmutable('-5 days'))->format('Y-m-d');
        $bookIssueId = $this->createBookIssueFixture(null, 'Student', null, 'Issued', $pastDueDate);

        $response = $this->withHeaders($headers)->post("api/v1/library/book-issues/{$bookIssueId}/return");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Returned', $body['status']);
        $this->assertEquals(10.0, $body['fine_amount']);
    }

    /**
     * BR-LIB-003: decided flat ₹500 replacement charge (ADR-009 §4).
     */
    public function testReportLostAppliesFlatReplacementCharge(): void
    {
        $user        = $this->createUser();
        $tokens      = $this->loginAs($user['username']);
        $headers     = $this->authHeaders($tokens['access_token']);
        $bookIssueId = $this->createBookIssueFixture();

        $response = $this->withHeaders($headers)->post("api/v1/library/book-issues/{$bookIssueId}/report-lost");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Lost', $body['status']);
        $this->assertEquals(500.0, $body['replacement_charge_amount']);

        $bookIssue = (new BookIssueModel())->find($bookIssueId);
        $this->assertSame('Lost', $bookIssue->status);
    }

    public function testSettleFineClearsTheBlock(): void
    {
        $user        = $this->createUser();
        $tokens      = $this->loginAs($user['username']);
        $headers     = $this->authHeaders($tokens['access_token']);
        $studentId   = $this->createStudentFixture();
        $bookIssueId = $this->createBookIssueFixture(null, 'Student', $studentId, 'Returned', '2026-08-01', 10.0, false);

        $this->withHeaders($headers)->post("api/v1/library/book-issues/{$bookIssueId}/settle-fine")->assertStatus(200);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/library/book-issues', [
            'book_id'         => $this->createBookFixture(),
            'borrower_type'   => 'Student',
            'borrower_ref_id' => $studentId,
            'due_date'        => '2026-08-20',
        ]);

        $response->assertStatus(201);
    }
}
