<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use Tests\Support\Fees\FeesTestCase;

/**
 * docs/ADR/ADR-012-document-pdf-module-scope-decisions.md
 *
 * @internal
 */
final class InvoicePdfTest extends FeesTestCase
{
    public function testGenerateInvoicePdf(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0);

        $response = $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/generate-pdf");

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('Invoice', $body['owner_type']);
        $this->assertSame($invoiceId, $body['owner_ref_id']);
        $this->assertSame('Invoice', $body['document_type']);
    }
}
