<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\DTOs\MarkNotificationFailedRequest;
use App\Modules\Communication\DTOs\NotificationLogResponse;
use App\Modules\Communication\Entities\NotificationLog;
use App\Modules\Communication\Gateways\EmailGatewayInterface;
use App\Modules\Communication\Gateways\SmsGatewayInterface;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;
use Throwable;

/**
 * docs/design/communication/Phase-3-Service-Controller-Design.md
 * docs/ADR/ADR-021-communication-sms-email-gateway.md — real dispatch,
 * behind SmsGatewayInterface/EmailGatewayInterface (§a). create() only
 * ever queues; dispatch() is the separate, explicit-trigger step (§e) —
 * no scheduler, matching every "no cron infrastructure" precedent.
 */
class NotificationLogService
{
    public function __construct(
        private readonly NotificationLogModel $notificationLogModel,
        private readonly AuditService $auditService,
        private readonly GuardianModel $guardianModel,
        private readonly StudentGuardianLinkModel $studentGuardianLinkModel,
        private readonly SmsGatewayInterface $smsGateway,
        private readonly EmailGatewayInterface $emailGateway,
    ) {
    }

    public function create(CreateNotificationLogRequest $request): NotificationLogResponse
    {
        $this->validateRecipient($request->recipientType, $request->recipientRefId);

        $id = $this->notificationLogModel->insert([
            'recipient_type'   => $request->recipientType,
            'recipient_ref_id' => $request->recipientRefId,
            'channel'          => $request->channel,
            'trigger_event'    => $request->triggerEvent,
            'message_body'     => $request->messageBody,
            'status'           => NotificationLog::STATUS_QUEUED,
        ], true);

        $notificationLog = $this->notificationLogModel->find($id);

        $this->auditService->record('NotificationLog', $id, AuditLog::ACTION_CREATE, null, $notificationLog->toRawArray());

        return new NotificationLogResponse($notificationLog);
    }

    /**
     * docs/ADR/ADR-021-communication-sms-email-gateway.md §d/§e — real
     * dispatch. Resolves real contact info per the recipient-type
     * matrix (Guardian direct, Student via primary-contact Guardian,
     * Employee/User unsupported), calls the matching gateway, and marks
     * Dispatched/Failed based on the outcome. Never throws an
     * unhandled exception up to the Controller — every failure mode
     * (unsupported recipient type, no primary guardian, gateway
     * failure) is captured as a Failed status with failure_reason.
     */
    public function dispatch(int $id): NotificationLogResponse
    {
        $before = $this->requireNotificationLog($id);

        if ($before->channel === NotificationLog::CHANNEL_PUSH) {
            return $this->fail($before, 'No push notification provider is configured — Push channel dispatch is out of scope (ADR-021 §d).');
        }

        try {
            $contact = $this->resolveContact($before->recipient_type, $before->recipient_ref_id);
        } catch (BusinessRuleException $e) {
            return $this->fail($before, $e->getMessage());
        }

        $message = $before->message_body ?? $before->trigger_event;

        try {
            if ($before->channel === NotificationLog::CHANNEL_SMS) {
                $this->smsGateway->send($contact['mobile'], $message);
            } else {
                $this->emailGateway->sendEmail($contact['email'], $before->trigger_event, $message);
            }
        } catch (Throwable $e) {
            return $this->fail($before, $e->getMessage());
        }

        return $this->changeStatus($id, NotificationLog::STATUS_DISPATCHED, [
            'dispatched_at' => Time::now()->toDateTimeString(),
        ]);
    }

    public function markDispatched(int $id): NotificationLogResponse
    {
        return $this->changeStatus($id, NotificationLog::STATUS_DISPATCHED, [
            'dispatched_at' => Time::now()->toDateTimeString(),
        ]);
    }

    public function markDelivered(int $id): NotificationLogResponse
    {
        return $this->changeStatus($id, NotificationLog::STATUS_DELIVERED);
    }

    /**
     * BR-COM-004: every failed delivery is logged with a reason.
     */
    public function markFailed(int $id, MarkNotificationFailedRequest $request): NotificationLogResponse
    {
        $before = $this->requireNotificationLog($id);

        return $this->fail($before, $request->failureReason);
    }

    public function getNotificationLog(int $id): NotificationLogResponse
    {
        return new NotificationLogResponse($this->requireNotificationLog($id));
    }

    /**
     * @return list<NotificationLogResponse>
     */
    public function listByRecipient(string $recipientType, int $recipientRefId): array
    {
        return array_map(
            static fn (NotificationLog $notificationLog): NotificationLogResponse => new NotificationLogResponse($notificationLog),
            $this->notificationLogModel->findByRecipient($recipientType, $recipientRefId),
        );
    }

    /**
     * docs/ADR/ADR-021-communication-sms-email-gateway.md §d — the
     * resolution matrix:
     *   Guardian -> Guardian.mobile_number/email directly.
     *   Student  -> the student's primary-contact Guardian
     *               (StudentGuardianLink.is_primary_contact), the
     *               same fallback-to-first-linked-guardian shape
     *               AttendanceService::notifyGuardianOfAbsence already
     *               uses.
     *   Employee/User -> genuinely no contact field in Appendix-G's
     *               approved schema for either entity. Not resolvable
     *               — throws so dispatch() marks the log Failed with a
     *               clear reason. Adding Employee/User contact fields
     *               is future HR & Payroll/Administration module scope,
     *               not invented here.
     *
     * @return array{mobile: string, email: string}
     */
    private function resolveContact(string $recipientType, int $recipientRefId): array
    {
        if ($recipientType === NotificationLog::RECIPIENT_GUARDIAN) {
            $guardian = $this->guardianModel->find($recipientRefId);

            if ($guardian === null) {
                throw new BusinessRuleException('GUARDIAN_NOT_FOUND', 'Guardian not found.');
            }

            return ['mobile' => $guardian->mobile_number, 'email' => (string) $guardian->email];
        }

        if ($recipientType === NotificationLog::RECIPIENT_STUDENT) {
            $links = $this->studentGuardianLinkModel->findByStudentId($recipientRefId);

            if ($links === []) {
                throw new BusinessRuleException('NO_GUARDIAN_LINKED', 'This student has no linked guardian to notify.');
            }

            $primary = null;

            foreach ($links as $link) {
                if ($link->is_primary_contact) {
                    $primary = $link;

                    break;
                }
            }

            $guardianId = ($primary ?? $links[0])->guardian_id;
            $guardian   = $this->guardianModel->find($guardianId);

            if ($guardian === null) {
                throw new BusinessRuleException('GUARDIAN_NOT_FOUND', 'Guardian not found.');
            }

            return ['mobile' => $guardian->mobile_number, 'email' => (string) $guardian->email];
        }

        // Employee/User: no phone/email field exists anywhere in
        // Appendix-G's approved schema for either entity (confirmed via
        // the Data Dictionary). This is a real, named limitation
        // (ADR-021 §d), not silently dropped.
        throw new BusinessRuleException(
            'NO_CONTACT_INFORMATION',
            "No contact information available for recipient_type {$recipientType} — Appendix-G does not model a phone/email field for this entity.",
        );
    }

    private function fail(NotificationLog $before, string $reason): NotificationLogResponse
    {
        $id = (int) $before->notification_log_id;

        $this->notificationLogModel->update($id, [
            'status'         => NotificationLog::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);

        $after = $this->notificationLogModel->find($id);

        $this->auditService->record(
            'NotificationLog',
            $id,
            AuditLog::ACTION_OVERRIDE,
            $before->toRawArray(),
            $after->toRawArray(),
            $reason,
        );

        return new NotificationLogResponse($after);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function changeStatus(int $id, string $status, array $extra = []): NotificationLogResponse
    {
        $before = $this->requireNotificationLog($id);

        $this->notificationLogModel->update($id, array_merge(['status' => $status], $extra));
        $after = $this->notificationLogModel->find($id);

        $this->auditService->record('NotificationLog', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new NotificationLogResponse($after);
    }

    private function validateRecipient(string $recipientType, int $recipientRefId): void
    {
        match ($recipientType) {
            NotificationLog::RECIPIENT_GUARDIAN => AppServices::guardianService()->getGuardian($recipientRefId),
            NotificationLog::RECIPIENT_EMPLOYEE => AppServices::employeeService()->assertEmployeeExists($recipientRefId),
            NotificationLog::RECIPIENT_USER     => AppServices::userService()->assertUserExists($recipientRefId),
            NotificationLog::RECIPIENT_STUDENT  => AppServices::studentService()->getStudent($recipientRefId),
            default                             => throw new BusinessRuleException('INVALID_RECIPIENT_TYPE', 'recipient_type must be one of Guardian, Employee, User, Student.'),
        };
    }

    private function requireNotificationLog(int $id): NotificationLog
    {
        $notificationLog = $this->notificationLogModel->find($id);

        if ($notificationLog === null) {
            throw new BusinessRuleException('NOTIFICATION_LOG_NOT_FOUND', 'Notification log not found.');
        }

        return $notificationLog;
    }
}
