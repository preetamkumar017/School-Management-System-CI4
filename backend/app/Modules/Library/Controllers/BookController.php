<?php

declare(strict_types=1);

namespace App\Modules\Library\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Library\DTOs\CreateBookRequest;
use App\Modules\Library\DTOs\UpdateBookRequest;
use App\Modules\Library\Entities\Book;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/library/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/library/books
 */
#[OA\Tag(name: 'Books')]
class BookController extends BaseController
{
    private const VALID_CLASSIFICATIONS = [Book::CLASSIFICATION_CIRCULATING, Book::CLASSIFICATION_REFERENCE];

    #[OA\Post(
        path: '/library/books',
        tags: ['Books'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/BookResponse')),
            new OA\Response(response: 422, description: 'BOOK_BARCODE_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $barcode        = (string) ($body['barcode'] ?? '');
        $title          = (string) ($body['title'] ?? '');
        $author         = isset($body['author']) && $body['author'] !== '' ? (string) $body['author'] : null;
        $classification = (string) ($body['classification'] ?? Book::CLASSIFICATION_CIRCULATING);

        $fields = [];

        if ($barcode === '' || strlen($barcode) > 30) {
            $fields['barcode'] = 'barcode is required and must be at most 30 characters.';
        }

        if ($title === '') {
            $fields['title'] = 'title is required.';
        }

        if (! in_array($classification, self::VALID_CLASSIFICATIONS, true)) {
            $fields['classification'] = 'classification must be one of Circulating, Reference.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::bookService()->createBook(new CreateBookRequest($barcode, $title, $author, $classification));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/library/books/{id}',
        tags: ['Books'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/BookResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $title          = (string) ($body['title'] ?? '');
        $author         = isset($body['author']) && $body['author'] !== '' ? (string) $body['author'] : null;
        $classification = (string) ($body['classification'] ?? Book::CLASSIFICATION_CIRCULATING);

        $fields = [];

        if ($title === '') {
            $fields['title'] = 'title is required.';
        }

        if (! in_array($classification, self::VALID_CLASSIFICATIONS, true)) {
            $fields['classification'] = 'classification must be one of Circulating, Reference.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::bookService()->updateBook($id, new UpdateBookRequest($title, $author, $classification));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/library/books/{id}',
        tags: ['Books'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/BookResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::bookService()->getBook($id)->toArray());
    }

    #[OA\Get(
        path: '/library/books',
        tags: ['Books'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/BookResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::bookService()->listBooks();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
