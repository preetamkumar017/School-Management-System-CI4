<?php

declare(strict_types=1);

namespace App\Modules\Communication\Gateways\Msg91;

use App\Modules\Communication\Gateways\EmailGatewayInterface;
use App\Modules\Communication\Gateways\GatewayException;
use App\Modules\Communication\Gateways\SmsGatewayInterface;
use Config\Notification as NotificationConfig;
use Config\Services;
use Throwable;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md §b — the first
 * concrete driver, MSG91 (https://msg91.com), implementing both
 * channel interfaces in one class since both share the same account
 * (authkey) and HTTP client — a single vendor integration, not two
 * independent ones. A future second vendor is a new class implementing
 * whichever interface(s) it supports; nothing here assumes MSG91 is the
 * only driver that will ever exist.
 *
 * Payload-building is isolated in one private method per channel
 * (buildSmsPayload/buildEmailPayload) so that if MSG91's exact field
 * names are slightly off from what's used here, fixing them is a
 * one-method change, not a redesign.
 */
class Msg91Gateway implements SmsGatewayInterface, EmailGatewayInterface
{
    public function __construct(
        private readonly NotificationConfig $config,
    ) {
    }

    /**
     * MSG91 Send SMS API (flow-based, v5):
     * POST https://control.msg91.com/api/v5/flow/
     */
    public function send(string $mobileNumber, string $message): void
    {
        $response = $this->request(
            $this->config->msg91SmsFlowUrl,
            $this->buildSmsPayload($mobileNumber, $message),
        );

        if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
            throw new GatewayException("MSG91 SMS send failed (HTTP {$response['statusCode']}): {$response['body']}");
        }
    }

    /**
     * MSG91 transactional Email API (v5):
     * POST https://api.msg91.com/api/v5/email/send
     */
    public function sendEmail(string $emailAddress, string $subject, string $message): void
    {
        $response = $this->request(
            $this->config->msg91EmailUrl,
            $this->buildEmailPayload($emailAddress, $subject, $message),
        );

        if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
            throw new GatewayException("MSG91 Email send failed (HTTP {$response['statusCode']}): {$response['body']}");
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{statusCode: int, body: string}
     */
    private function request(string $url, array $payload): array
    {
        try {
            $client = Services::curlrequest();

            $response = $client->request('POST', $url, [
                'headers' => [
                    'authkey'      => $this->config->msg91AuthKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json'            => $payload,
                'http_errors'     => false,
                'timeout'         => 10,
                'connect_timeout' => 5,
            ]);

            return [
                'statusCode' => $response->getStatusCode(),
                'body'       => $response->getBody(),
            ];
        } catch (Throwable $e) {
            throw new GatewayException('MSG91 request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSmsPayload(string $mobileNumber, string $message): array
    {
        return [
            'sender'    => $this->config->msg91SenderId,
            'short_url' => '0',
            'mobiles'   => '91' . $mobileNumber,
            'message'   => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmailPayload(string $emailAddress, string $subject, string $message): array
    {
        return [
            'to' => [
                ['email' => $emailAddress],
            ],
            'from' => [
                'email' => $this->config->msg91FromEmail,
            ],
            'domain'  => $this->config->msg91FromEmailDomain,
            'subject' => $subject,
            'body'    => $message,
        ];
    }
}
