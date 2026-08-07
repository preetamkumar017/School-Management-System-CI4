<?php

declare(strict_types=1);

namespace Tests\Unit\Communication;

use App\Modules\Communication\Gateways\Msg91\Msg91Gateway;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Notification as NotificationConfig;
use ReflectionMethod;

/**
 * Narrow, offline test for Msg91Gateway's payload-building methods —
 * exercised via reflection since they're private and no network call is
 * made. Verifies the isolated-per-channel structure ADR-021 §b
 * describes: a field-name fix to one payload never touches the other.
 *
 * @internal
 */
final class Msg91GatewayPayloadTest extends CIUnitTestCase
{
    private function gateway(): Msg91Gateway
    {
        $config               = new NotificationConfig();
        $config->msg91AuthKey  = 'test-authkey';
        $config->msg91SenderId = 'SCHOOL';
        $config->msg91FromEmail = 'no-reply@school.example';

        return new Msg91Gateway($config);
    }

    public function testBuildSmsPayloadPrefixesCountryCodeAndIncludesSenderAndMessage(): void
    {
        $method = new ReflectionMethod(Msg91Gateway::class, 'buildSmsPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($this->gateway(), '9876543210', 'Test message body.');

        $this->assertSame('SCHOOL', $payload['sender']);
        $this->assertSame('919876543210', $payload['mobiles']);
        $this->assertSame('Test message body.', $payload['message']);
    }

    public function testBuildEmailPayloadIncludesToFromSubjectAndBody(): void
    {
        $method = new ReflectionMethod(Msg91Gateway::class, 'buildEmailPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($this->gateway(), 'guardian@example.com', 'Absence alert', 'Your child was marked absent.');

        $this->assertSame('guardian@example.com', $payload['to'][0]['email']);
        $this->assertSame('no-reply@school.example', $payload['from']['email']);
        $this->assertSame('Absence alert', $payload['subject']);
        $this->assertSame('Your child was marked absent.', $payload['body']);
    }
}
