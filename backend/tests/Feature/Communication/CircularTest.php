<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Communication\CommunicationTestCase;

/**
 * @internal
 */
final class CircularTest extends CommunicationTestCase
{
    public function testCreateAndRetractCircular(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/communication/circulars', [
            'author_id'       => $user['id'],
            'post_type'       => 'Homework',
            'title'           => 'Weekend Math Homework',
            'body'            => 'Complete exercises 4.1-4.3.',
            'target_audience' => 'Class 6-A',
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('Posted', $body['status']);
        $circularId = $body['circular_id'];

        $retract = $this->withHeaders($headers)->post("api/v1/communication/circulars/{$circularId}/retract");
        $retract->assertStatus(200);
        $this->assertSame('Retracted', $this->decode($retract)['data']['status']);
    }

    public function testRetractingAlreadyRetractedCircularIsRejected(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $circularId = $this->createCircularFixture($user['id'], 'Circular', 'All', 'Retracted');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/communication/circulars/{$circularId}/retract"),
            BusinessRuleException::class,
            'CIRCULAR_ALREADY_RETRACTED',
            422,
        );
    }

    public function testListByTargetAudience(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->createCircularFixture($user['id'], 'Announcement', 'Class 8-B');
        $this->createCircularFixture($user['id'], 'Announcement', 'Class 8-B');
        $this->createCircularFixture($user['id'], 'Announcement', 'All');

        $response = $this->withHeaders($headers)->get('api/v1/communication/circulars?target_audience=Class%208-B');
        $response->assertStatus(200);
        $this->assertCount(2, $this->decode($response)['data']);
    }
}
