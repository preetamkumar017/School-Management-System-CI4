<?php

declare(strict_types=1);

namespace App\Modules\Library\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Library\DTOs\IssueBookRequest;
use App\Modules\Library\Entities\BookIssue;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/library/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/library/book-issues
 */
#[OA\Tag(name: 'Book Issues')]
class BookIssueController extends BaseController
{
    private const VALID_BORROWER_TYPES = [BookIssue::BORROWER_STUDENT, BookIssue::BORROWER_EMPLOYEE];

    #[OA\Post(
        path: '/library/book-issues',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookIssueCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/BookIssueResponse')),
            new OA\Response(response: 422, description: 'MAX_BOOKS_LIMIT_REACHED / OUTSTANDING_FINE_BLOCKS_ISSUE / BOOK_NOT_CIRCULATING.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $bookId        = (int) ($body['book_id'] ?? 0);
        $borrowerType  = (string) ($body['borrower_type'] ?? '');
        $borrowerRefId = (int) ($body['borrower_ref_id'] ?? 0);
        $dueDate       = (string) ($body['due_date'] ?? '');

        $fields = [];

        if ($bookId <= 0) {
            $fields['book_id'] = 'book_id is required.';
        }

        if (! in_array($borrowerType, self::VALID_BORROWER_TYPES, true)) {
            $fields['borrower_type'] = 'borrower_type must be one of Student, Employee.';
        }

        if ($borrowerRefId <= 0) {
            $fields['borrower_ref_id'] = 'borrower_ref_id is required.';
        }

        if ($dueDate === '') {
            $fields['due_date'] = 'due_date is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::bookIssueService()->issueBook(
            new IssueBookRequest($bookId, $borrowerType, $borrowerRefId, $dueDate),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/library/book-issues/{id}/return',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Returned (BR-LIB-002).', content: new OA\JsonContent(ref: '#/components/schemas/BookIssueResponse'))],
    )]
    public function returnBook(int $id)
    {
        return $this->respondSuccess(Services::bookIssueService()->returnBook($id)->toArray());
    }

    #[OA\Post(
        path: '/library/book-issues/{id}/report-lost',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Reported lost (BR-LIB-003).', content: new OA\JsonContent(ref: '#/components/schemas/BookIssueResponse'))],
    )]
    public function reportLost(int $id)
    {
        return $this->respondSuccess(Services::bookIssueService()->reportLost($id)->toArray());
    }

    #[OA\Post(
        path: '/library/book-issues/{id}/settle-fine',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Settled.', content: new OA\JsonContent(ref: '#/components/schemas/BookIssueResponse'))],
    )]
    public function settleFine(int $id)
    {
        return $this->respondSuccess(Services::bookIssueService()->settleFine($id)->toArray());
    }

    #[OA\Get(
        path: '/library/book-issues/{id}',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/BookIssueResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::bookIssueService()->getBookIssue($id)->toArray());
    }

    #[OA\Get(
        path: '/library/book-issues',
        tags: ['Book Issues'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'borrower_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['Student', 'Employee'])),
            new OA\Parameter(name: 'borrower_ref_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/BookIssueResponse')),
            ),
        ],
    )]
    public function index()
    {
        $borrowerType  = (string) ($this->request->getGet('borrower_type') ?? '');
        $borrowerRefId = (int) ($this->request->getGet('borrower_ref_id') ?? 0);

        $fields = [];

        if (! in_array($borrowerType, self::VALID_BORROWER_TYPES, true)) {
            $fields['borrower_type'] = 'borrower_type query parameter must be one of Student, Employee.';
        }

        if ($borrowerRefId <= 0) {
            $fields['borrower_ref_id'] = 'borrower_ref_id query parameter is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::bookIssueService()->listByBorrower($borrowerType, $borrowerRefId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
