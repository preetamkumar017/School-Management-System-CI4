<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * docs/ADR/ADR-012-document-pdf-module-scope-decisions.md
 *
 * @internal
 */
final class PayslipPdfTest extends HrPayrollTestCase
{
    public function testGeneratePayslipRequiresProcessedStatus(): void
    {
        $user         = $this->createUser();
        $tokens       = $this->loginAs($user['username']);
        $headers      = $this->authHeaders($tokens['access_token']);
        $payrollRunId = $this->createPayrollRunFixture(null, '2026-07', 50000.0, 'Draft');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/hr-payroll/payroll-runs/{$payrollRunId}/generate-payslip"),
            BusinessRuleException::class,
            'PAYROLL_RUN_NOT_PROCESSED',
            422,
        );
    }

    public function testGeneratePayslipSucceedsForProcessedRun(): void
    {
        $user         = $this->createUser();
        $tokens       = $this->loginAs($user['username']);
        $headers      = $this->authHeaders($tokens['access_token']);
        $payrollRunId = $this->createPayrollRunFixture(null, '2026-07', 50000.0, 'Processed');

        $response = $this->withHeaders($headers)->post("api/v1/hr-payroll/payroll-runs/{$payrollRunId}/generate-payslip");

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('PayrollRun', $body['owner_type']);
        $this->assertSame('Payslip', $body['document_type']);
    }
}
