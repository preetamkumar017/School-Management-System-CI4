<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-8-Document-Design.md
 * Base path /api/v1/administration/documents
 * No POST / — generation is triggered from each owning module's own
 * Controller (ADR-012 §1, Phase 8's "Controller" section).
 */
#[OA\Tag(name: 'Documents')]
class DocumentController extends BaseController
{
    #[OA\Get(
        path: '/administration/documents/{id}',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::documentService()->getDocument($id)->toArray());
    }

    #[OA\Get(
        path: '/administration/documents/{id}/download',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The PDF file stream.')],
    )]
    public function download(int $id)
    {
        $document     = Services::documentService()->getDocument($id);
        $absolutePath = Services::documentService()->getAbsolutePath($id);

        return $this->response->download($absolutePath, null)->setFileName($document->documentType . '-' . $document->ownerRefId . '.pdf');
    }

    #[OA\Get(
        path: '/administration/documents',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'owner_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['Application', 'Student', 'Invoice', 'ReportCard', 'PayrollRun'])),
            new OA\Parameter(name: 'owner_ref_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DocumentResponse')),
            ),
        ],
    )]
    public function index()
    {
        $ownerType  = (string) ($this->request->getGet('owner_type') ?? '');
        $ownerRefId = (int) ($this->request->getGet('owner_ref_id') ?? 0);

        $fields = [];

        if ($ownerType === '') {
            $fields['owner_type'] = 'owner_type query parameter is required.';
        }

        if ($ownerRefId <= 0) {
            $fields['owner_ref_id'] = 'owner_ref_id query parameter is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::documentService()->listByOwner($ownerType, $ownerRefId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
