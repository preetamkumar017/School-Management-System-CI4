<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
final class FeeHeadTest extends FeesTestCase
{
    public function testCreateAndUpdateFeeHead(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-heads', [
            'fee_head_name' => 'Tuition Fee',
            'is_taxable'    => false,
        ]);
        $create->assertStatus(201);
        $id = $this->decode($create)['data']['fee_head_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/fees/fee-heads/{$id}", [
            'fee_head_name' => 'Tuition Fee (Revised)',
            'is_taxable'    => true,
            'gst_rate'      => 18,
        ]);
        $update->assertStatus(200);
        $this->assertSame('Tuition Fee (Revised)', $this->decode($update)['data']['fee_head_name']);
        $this->assertTrue($this->decode($update)['data']['is_taxable']);
    }

    public function testDuplicateFeeHeadNameIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-heads', [
            'fee_head_name' => 'Exam Fee',
            'is_taxable'    => false,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-heads', [
                'fee_head_name' => 'Exam Fee',
                'is_taxable'    => false,
            ]),
            BusinessRuleException::class,
            'FEE_HEAD_NAME_ALREADY_TAKEN',
            422,
        );
    }
}
