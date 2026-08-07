<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\ConfigurationModel;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md, ADR-011
 *
 * @internal
 */
final class ConfigurationTest extends AdministrationTestCase
{
    public function testSeededKeysAreReadableAtCreationDefaults(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->get('api/v1/administration/configurations/library.max_books_per_borrower');

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('3', $body['setting_value']);
        $this->assertSame('Number', $body['data_type']);
        $this->assertSame('Library', $body['module']);
        $this->assertTrue($body['is_editable']);
    }

    public function testUpdateChangesTheValue(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->patch('api/v1/administration/configurations/library.max_books_per_borrower', [
            'setting_value' => '5',
        ]);

        $response->assertStatus(200);
        $this->assertSame('5', $this->decode($response)['data']['setting_value']);

        $row = (new ConfigurationModel())->findByKey('library.max_books_per_borrower');
        $this->assertSame('5', $row->setting_value);
    }

    public function testUpdateRejectedWhenNotEditable(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        (new ConfigurationModel())->update(
            (new ConfigurationModel())->findByKey('library.max_books_per_borrower')->setting_id,
            ['is_editable' => false],
        );

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->patch('api/v1/administration/configurations/library.max_books_per_borrower', [
                'setting_value' => '5',
            ]),
            BusinessRuleException::class,
            'CONFIGURATION_NOT_EDITABLE',
            422,
        );
    }

    public function testListByModule(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->get('api/v1/administration/configurations?module=Library');

        $response->assertStatus(200);
        // 4 from ADR-011 §4 plus library.reservation_response_window_hours
        // (ADR-017 §4).
        $this->assertCount(5, $this->decode($response)['data']);
    }
}
