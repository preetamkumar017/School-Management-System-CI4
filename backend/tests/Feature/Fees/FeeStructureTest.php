<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
final class FeeStructureTest extends FeesTestCase
{
    public function testCreateAndUpdateFeeStructure(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $feeHeadId = $this->createFeeHeadFixture();
        $sessionId = $this->createAcademicSession();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-structures', [
            'class_id'            => $classId,
            'fee_head_id'         => $feeHeadId,
            'academic_session_id' => $sessionId,
            'category'            => 'GENERAL',
            'amount'              => 5000,
        ]);
        $create->assertStatus(201);
        $id = $this->decode($create)['data']['fee_structure_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/fees/fee-structures/{$id}", [
            'amount' => 5500,
        ]);
        $update->assertStatus(200);
        $this->assertEquals(5500.0, $this->decode($update)['data']['amount']);

        $list = $this->withHeaders($headers)->get("api/v1/fees/fee-structures?class_id={$classId}&academic_session_id={$sessionId}&category=GENERAL");
        $list->assertStatus(200);
        $this->assertCount(1, $this->decode($list)['data']);
    }

    public function testDuplicateFeeStructureIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $feeHeadId = $this->createFeeHeadFixture();
        $sessionId = $this->createAcademicSession();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-structures', [
            'class_id'            => $classId,
            'fee_head_id'         => $feeHeadId,
            'academic_session_id' => $sessionId,
            'category'            => 'GENERAL',
            'amount'              => 5000,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/fee-structures', [
                'class_id'            => $classId,
                'fee_head_id'         => $feeHeadId,
                'academic_session_id' => $sessionId,
                'category'            => 'GENERAL',
                'amount'              => 4500,
            ]),
            BusinessRuleException::class,
            'FEE_STRUCTURE_ALREADY_EXISTS',
            422,
        );
    }
}
