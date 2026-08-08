<?php

declare(strict_types=1);

namespace App\Modules\Library\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Library\DTOs\BookResponse;
use App\Modules\Library\DTOs\CreateBookRequest;
use App\Modules\Library\DTOs\UpdateBookRequest;
use App\Modules\Library\Entities\Book;
use App\Modules\Library\Models\BookModel;

/**
 * docs/design/library/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3, Phase 2): `library.manage` (Tier 1 only) gates writes.
 */
class BookService
{
    public const PERMISSION_MANAGE = 'library.manage';

    public function __construct(
        private readonly BookModel $bookModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createBook(CreateBookRequest $request): BookResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->bookModel->existsByBarcode($request->barcode)) {
            throw new BusinessRuleException('BOOK_BARCODE_ALREADY_EXISTS', 'A book with this barcode already exists.');
        }

        $id = $this->bookModel->insert([
            'barcode'        => $request->barcode,
            'title'          => $request->title,
            'author'         => $request->author,
            'classification' => $request->classification,
            'is_available'   => true,
        ], true);

        $book = $this->bookModel->find($id);

        $this->auditService->record('Book', $id, AuditLog::ACTION_CREATE, null, $book->toRawArray());

        return new BookResponse($book);
    }

    public function updateBook(int $id, UpdateBookRequest $request): BookResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireBook($id);

        $this->bookModel->update($id, [
            'title'          => $request->title,
            'author'         => $request->author,
            'classification' => $request->classification,
        ]);

        $after = $this->bookModel->find($id);

        $this->auditService->record('Book', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookResponse($after);
    }

    public function getBook(int $id): BookResponse
    {
        return new BookResponse($this->requireBook($id));
    }

    /**
     * @return list<BookResponse>
     */
    public function listBooks(): array
    {
        return array_map(
            static fn (Book $book): BookResponse => new BookResponse($book),
            $this->bookModel->findAll(),
        );
    }

    private function requireBook(int $id): Book
    {
        $book = $this->bookModel->find($id);

        if ($book === null) {
            throw new BusinessRuleException('BOOK_NOT_FOUND', 'Book not found.');
        }

        return $book;
    }
}
