<?php

declare(strict_types=1);

namespace Tests\Support\Communication;

use App\Modules\Communication\Models\CircularModel;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Transport\TransportTestCase;

/**
 * @internal
 */
abstract class CommunicationTestCase extends TransportTestCase
{
    protected function createCircularFixture(
        ?int $authorId = null,
        string $postType = 'Announcement',
        string $targetAudience = 'All',
        string $status = 'Posted',
    ): int {
        return (new CircularModel())->insert([
            'author_id'       => $authorId ?? $this->createUser()['id'],
            'post_type'       => $postType,
            'title'           => 'Title ' . uniqid('', true),
            'body'            => 'Body content.',
            'target_audience' => $targetAudience,
            'posted_at'       => Time::now()->toDateTimeString(),
            'status'          => $status,
        ], true);
    }

    protected function createNotificationLogFixture(
        string $recipientType = 'User',
        ?int $recipientRefId = null,
        string $channel = 'Email',
        string $status = 'Queued',
    ): int {
        return (new NotificationLogModel())->insert([
            'recipient_type'   => $recipientType,
            'recipient_ref_id' => $recipientRefId ?? $this->createUser()['id'],
            'channel'          => $channel,
            'trigger_event'    => 'Test event',
            'status'           => $status,
        ], true);
    }

    protected function linkGuardianToStudent(int $studentId, int $guardianId, bool $isPrimaryContact = true): void
    {
        (new StudentGuardianLinkModel())->insert([
            'student_id'          => $studentId,
            'guardian_id'         => $guardianId,
            'is_primary_contact'  => $isPrimaryContact,
        ]);
    }
}
