<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\Document;

/**
 * docs/design/administration/Phase-8-Document-Design.md
 */
final class DocumentResponse
{
    public readonly int $documentId;
    public readonly string $ownerType;
    public readonly int $ownerRefId;
    public readonly string $documentType;
    public readonly string $filePath;
    public readonly int $uploadedBy;
    public readonly string $uploadedAt;

    public function __construct(Document $document)
    {
        $this->documentId   = $document->document_id;
        $this->ownerType    = $document->owner_type;
        $this->ownerRefId   = $document->owner_ref_id;
        $this->documentType = $document->document_type;
        $this->filePath     = $document->file_path;
        $this->uploadedBy   = $document->uploaded_by;
        $this->uploadedAt   = $document->uploaded_at;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'owner_type'    => $this->ownerType,
            'owner_ref_id'  => $this->ownerRefId,
            'document_type' => $this->documentType,
            'file_path'     => $this->filePath,
            'uploaded_by'   => $this->uploadedBy,
            'uploaded_at'   => $this->uploadedAt,
        ];
    }
}
