<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

use App\Modules\Library\Entities\Book;

/**
 * docs/design/library/Phase-2-Model-DTO-Design.md
 */
final class BookResponse
{
    public readonly int $bookId;
    public readonly string $barcode;
    public readonly string $title;
    public readonly ?string $author;
    public readonly string $classification;
    public readonly bool $isAvailable;

    public function __construct(Book $book)
    {
        $this->bookId         = $book->book_id;
        $this->barcode        = $book->barcode;
        $this->title          = $book->title;
        $this->author         = $book->author;
        $this->classification = $book->classification;
        $this->isAvailable    = $book->is_available;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'book_id'        => $this->bookId,
            'barcode'        => $this->barcode,
            'title'          => $this->title,
            'author'         => $this->author,
            'classification' => $this->classification,
            'is_available'   => $this->isAvailable,
        ];
    }
}
