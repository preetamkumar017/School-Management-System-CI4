<?php

declare(strict_types=1);

namespace App\Modules\Communication\Gateways;

use RuntimeException;

/**
 * Thrown by any SmsGatewayInterface/EmailGatewayInterface implementation
 * on a failed send (non-2xx response, network failure, malformed
 * response). NotificationLogService::dispatch() catches this and marks
 * the log Failed with the exception message as failure_reason — it is
 * never allowed to propagate to the Controller as an unhandled 500.
 */
class GatewayException extends RuntimeException
{
}
