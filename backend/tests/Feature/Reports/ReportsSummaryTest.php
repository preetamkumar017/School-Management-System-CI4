<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Tests\Support\Communication\CommunicationTestCase;

/**
 * @internal
 */
final class ReportsSummaryTest extends CommunicationTestCase
{
    public function testSummaryComposesCountsFromEveryModule(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->createEmployeeFixture();
        $this->createBookFixture();
        $vehicleId = $this->createVehicleFixture();
        $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId);
        $this->createFeeHeadFixture();

        $response = $this->withHeaders($headers)->get('api/v1/reports/summary');

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        $this->assertArrayHasKey('generated_at', $body);
        $this->assertGreaterThanOrEqual(1, $body['total_users']);
        $this->assertGreaterThanOrEqual(1, $body['total_employees']);
        $this->assertGreaterThanOrEqual(1, $body['total_books']);
        $this->assertGreaterThanOrEqual(1, $body['total_vehicles']);
        $this->assertGreaterThanOrEqual(1, $body['total_routes']);
        $this->assertGreaterThanOrEqual(1, $body['total_fee_heads']);
    }
}
