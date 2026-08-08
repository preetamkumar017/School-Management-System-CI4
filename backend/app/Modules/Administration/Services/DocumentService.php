<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\DocumentResponse;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\Document;
use App\Modules\Administration\Models\DocumentModel;
use CodeIgniter\I18n\Time;

/**
 * docs/design/administration/Phase-8-Document-Design.md
 * Called only by the three PDF-generating Services (ADR-012 §3) — no
 * public "create" endpoint, matching Appendix-G's own Lifecycle line.
 */
class DocumentService
{
    private const STORAGE_SUBDIR = 'documents';

    public function __construct(
        private readonly DocumentModel $documentModel,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Writes $fileBytes under writable/uploads/documents/{ownerType}/ and
     * records the relative file_path (ADR-012 §2 — local disk, portable
     * across environments).
     *
     * ADR-023 §2: generalized from a PDF-only method to accept any file
     * extension, so it can store an ID-card PDF and a student's photo
     * upload (JPEG/PNG) alike. $fileExtension defaults to 'pdf' so the
     * three pre-existing call sites (InvoiceService, PayrollRunService,
     * ReportCardService) are byte-for-byte unchanged.
     */
    public function store(string $ownerType, int $ownerRefId, string $documentType, string $fileBytes, int $uploadedBy, string $fileExtension = 'pdf'): DocumentResponse
    {
        $relativeDir = self::STORAGE_SUBDIR . '/' . $ownerType;
        $absoluteDir = WRITEPATH . 'uploads/' . $relativeDir;

        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $fileName     = $ownerRefId . '-' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $relativePath = $relativeDir . '/' . $fileName;

        file_put_contents($absoluteDir . '/' . $fileName, $fileBytes);

        $id = $this->documentModel->insert([
            'owner_type'    => $ownerType,
            'owner_ref_id'  => $ownerRefId,
            'document_type' => $documentType,
            'file_path'     => $relativePath,
            'uploaded_by'   => $uploadedBy,
            'uploaded_at'   => Time::now()->toDateTimeString(),
        ], true);

        $document = $this->documentModel->find($id);

        $this->auditService->record('Document', $id, AuditLog::ACTION_CREATE, null, $document->toRawArray());

        return new DocumentResponse($document);
    }

    public function getDocument(int $id): DocumentResponse
    {
        return new DocumentResponse($this->requireDocument($id));
    }

    public function getAbsolutePath(int $id): string
    {
        return WRITEPATH . 'uploads/' . $this->requireDocument($id)->file_path;
    }

    /**
     * @return list<DocumentResponse>
     */
    public function listByOwner(string $ownerType, int $ownerRefId): array
    {
        return array_map(
            static fn (Document $document): DocumentResponse => new DocumentResponse($document),
            $this->documentModel->findByOwner($ownerType, $ownerRefId),
        );
    }

    private function requireDocument(int $id): Document
    {
        $document = $this->documentModel->find($id);

        if ($document === null) {
            throw new BusinessRuleException('DOCUMENT_NOT_FOUND', 'Document not found.');
        }

        return $document;
    }
}
