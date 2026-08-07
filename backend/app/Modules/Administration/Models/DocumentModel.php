<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\Document;

/**
 * docs/design/administration/Phase-8-Document-Design.md
 */
class DocumentModel extends BaseModel
{
    protected $table          = 'documents';
    protected $primaryKey     = 'document_id';
    protected $returnType     = Document::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'owner_type',
        'owner_ref_id',
        'document_type',
        'file_path',
        'uploaded_by',
        'uploaded_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<Document>
     */
    public function findByOwner(string $ownerType, int $ownerRefId): array
    {
        return $this->where('owner_type', $ownerType)
            ->where('owner_ref_id', $ownerRefId)
            ->orderBy('uploaded_at', 'DESC')
            ->findAll();
    }
}
