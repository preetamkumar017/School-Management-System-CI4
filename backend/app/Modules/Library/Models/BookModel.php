<?php

declare(strict_types=1);

namespace App\Modules\Library\Models;

use App\Core\BaseModel;
use App\Modules\Library\Entities\Book;

/**
 * docs/design/library/Phase-2-Model-DTO-Design.md
 */
class BookModel extends BaseModel
{
    protected $table          = 'books';
    protected $primaryKey     = 'book_id';
    protected $returnType     = Book::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'barcode',
        'title',
        'author',
        'classification',
        'is_available',
        'created_by',
        'updated_by',
    ];

    public function findByBarcode(string $value): ?Book
    {
        return $this->where('barcode', $value)->first();
    }

    public function existsByBarcode(string $value): bool
    {
        return $this->where('barcode', $value)->countAllResults() > 0;
    }

    public function existsByBarcodeExceptId(string $value, int $id): bool
    {
        return $this->where('barcode', $value)->where('book_id !=', $id)->countAllResults() > 0;
    }
}
