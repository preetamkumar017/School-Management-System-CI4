<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Communication\DTOs\CircularResponse;
use App\Modules\Communication\DTOs\CreateCircularRequest;
use App\Modules\Communication\Entities\Circular;
use App\Modules\Communication\Models\CircularModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/communication/Phase-3-Service-Controller-Design.md
 */
class CircularService
{
    public function __construct(
        private readonly CircularModel $circularModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createCircular(CreateCircularRequest $request): CircularResponse
    {
        AppServices::userService()->assertUserExists($request->authorId);

        $id = $this->circularModel->insert([
            'author_id'       => $request->authorId,
            'post_type'       => $request->postType,
            'title'           => $request->title,
            'body'            => $request->body,
            'target_audience' => $request->targetAudience,
            'posted_at'       => Time::now()->toDateTimeString(),
            'status'          => Circular::STATUS_POSTED,
        ], true);

        $circular = $this->circularModel->find($id);

        $this->auditService->record('Circular', $id, AuditLog::ACTION_CREATE, null, $circular->toRawArray());

        return new CircularResponse($circular);
    }

    public function retract(int $id): CircularResponse
    {
        $before = $this->requireCircular($id);

        if ($before->status === Circular::STATUS_RETRACTED) {
            throw new BusinessRuleException('CIRCULAR_ALREADY_RETRACTED', 'This circular has already been retracted.');
        }

        $this->circularModel->update($id, ['status' => Circular::STATUS_RETRACTED]);
        $after = $this->circularModel->find($id);

        $this->auditService->record('Circular', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new CircularResponse($after);
    }

    public function getCircular(int $id): CircularResponse
    {
        return new CircularResponse($this->requireCircular($id));
    }

    /**
     * @return list<CircularResponse>
     */
    public function listByTargetAudience(string $targetAudience): array
    {
        return array_map(
            static fn (Circular $circular): CircularResponse => new CircularResponse($circular),
            $this->circularModel->findByTargetAudience($targetAudience),
        );
    }

    private function requireCircular(int $id): Circular
    {
        $circular = $this->circularModel->find($id);

        if ($circular === null) {
            throw new BusinessRuleException('CIRCULAR_NOT_FOUND', 'Circular not found.');
        }

        return $circular;
    }
}
