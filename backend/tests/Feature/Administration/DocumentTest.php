<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Fees\FeesTestCase;

/**
 * docs/design/administration/Phase-8-Document-Design.md, ADR-012
 *
 * @internal
 */
final class DocumentTest extends FeesTestCase
{
    public function testListByOwnerAfterGeneration(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0);

        $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/generate-pdf")->assertStatus(201);

        $response = $this->withHeaders($headers)->get("api/v1/administration/documents?owner_type=Invoice&owner_ref_id={$invoiceId}");
        $response->assertStatus(200);
        $this->assertCount(1, $this->decode($response)['data']);
    }

    public function testShowMissingDocumentReturns422(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get('api/v1/administration/documents/999999'),
            BusinessRuleException::class,
            'DOCUMENT_NOT_FOUND',
            422,
        );
    }
}
