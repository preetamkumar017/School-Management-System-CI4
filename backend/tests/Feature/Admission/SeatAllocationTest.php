<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Admission\AdmissionTestCase;

/**
 * @internal
 */
final class SeatAllocationTest extends AdmissionTestCase
{
    public function testCreateAndUpdateCapacity(): void
    {
        $user     = $this->createUser();
        $tokens   = $this->loginAs($user['username']);
        $headers  = $this->authHeaders($tokens['access_token']);
        $classId  = $this->createClassFixture();
        $sessionId = $this->createAcademicSession();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'total_capacity'      => 40,
            'rte_quota_capacity'  => 10,
        ]);
        $create->assertStatus(201);
        $id = $this->decode($create)['data']['seat_allocation_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/admission/seat-allocations/{$id}", [
            'total_capacity'     => 50,
            'rte_quota_capacity' => 12,
        ]);
        $update->assertStatus(200);
        $this->assertSame(50, $this->decode($update)['data']['total_capacity']);

        $find = $this->withHeaders($headers)->get("api/v1/admission/seat-allocations?class_id={$classId}&academic_session_id={$sessionId}");
        $find->assertStatus(200);
        $this->assertSame($id, $this->decode($find)['data']['seat_allocation_id']);
    }

    public function testRteQuotaExceedingCeilingIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
                'class_id'            => $this->createClassFixture(),
                'academic_session_id' => $this->createAcademicSession(),
                'total_capacity'      => 40,
                'rte_quota_capacity'  => 11,
            ]),
            BusinessRuleException::class,
            'SEAT_ALLOCATION_RTE_QUOTA_EXCEEDS_CEILING',
            422,
        );
    }

    public function testDuplicateAllocationForSameClassAndSessionIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sessionId = $this->createAcademicSession();

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'total_capacity'      => 40,
            'rte_quota_capacity'  => 10,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
                'class_id'            => $classId,
                'academic_session_id' => $sessionId,
                'total_capacity'      => 20,
                'rte_quota_capacity'  => 5,
            ]),
            BusinessRuleException::class,
            'SEAT_ALLOCATION_ALREADY_EXISTS',
            422,
        );
    }

    public function testCapacityCannotBeReducedBelowSeatsFilled(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $seatAllocationId = $this->createSeatAllocationFixture(null, null, 40, 10);

        (new \App\Modules\Admission\Models\SeatAllocationModel())->update($seatAllocationId, ['seats_filled' => 35]);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->patch("api/v1/admission/seat-allocations/{$seatAllocationId}", [
                    'total_capacity'     => 30,
                    'rte_quota_capacity' => 5,
                ]),
            BusinessRuleException::class,
            'SEAT_ALLOCATION_CAPACITY_BELOW_SEATS_FILLED',
            422,
        );
    }
}
