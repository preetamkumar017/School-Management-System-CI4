<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
final class ScholarshipWaiverTest extends FeesTestCase
{
    public function testCreateAndListWaiversForStudent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $feeHeadId = $this->createFeeHeadFixture();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/scholarship-waivers', [
            'student_id'    => $studentId,
            'fee_head_id'   => $feeHeadId,
            'waiver_type'   => 'RTE',
            'waiver_amount' => 1500,
        ]);
        $create->assertStatus(201);

        $list = $this->withHeaders($headers)->get("api/v1/fees/scholarship-waivers?student_id={$studentId}");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);
        $this->assertSame('RTE', $this->decode($list)['data'][0]['waiver_type']);
    }
}
