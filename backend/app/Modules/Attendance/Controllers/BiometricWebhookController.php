<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Core\BaseController;
use Config\Services;

/**
 * Endpoint for biometric devices to push attendance logs.
 * Typically this would be protected by an API Key instead of JWT, 
 * but for this implementation we assume either basic auth or a secret token 
 * in the payload / header.
 */
class BiometricWebhookController extends BaseController
{
    public function receive()
    {
        // Example Payload: 
        // { "employee_id": 1, "punch_time": "2026-08-09 08:05:00", "punch_type": "In", "device_id": "MAIN_GATE" }
        
        $body = $this->request->getJSON(true);
        if (!$body) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid JSON payload.']);
        }

        $employeeId = (int) ($body['employee_id'] ?? 0);
        $punchTime  = $body['punch_time'] ?? null;
        $punchType  = $body['punch_type'] ?? null;
        $deviceId   = $body['device_id'] ?? 'API';

        if (!$employeeId || !$punchTime || !$punchType) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Missing required fields.']);
        }

        try {
            Services::staffPunchService()->recordPunch($employeeId, $punchTime, $punchType, 'Biometric', $deviceId);
            return $this->respondSuccess(['message' => 'Punch recorded successfully.']);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
