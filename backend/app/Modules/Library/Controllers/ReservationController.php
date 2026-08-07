<?php

declare(strict_types=1);

namespace App\Modules\Library\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Library\DTOs\CreateReservationRequest;
use App\Modules\Library\Entities\Reservation;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md — BR-LIB-006.
 * Base path /api/v1/library/reservations
 */
#[OA\Tag(name: 'Reservations')]
class ReservationController extends BaseController
{
    private const VALID_BORROWER_TYPES = [Reservation::BORROWER_STUDENT, Reservation::BORROWER_EMPLOYEE];

    #[OA\Post(
        path: '/library/reservations',
        tags: ['Reservations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ReservationCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/ReservationResponse')),
            new OA\Response(response: 422, description: 'RESERVATION_NOT_NEEDED / RESERVATION_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $bookId        = (int) ($body['book_id'] ?? 0);
        $borrowerType  = (string) ($body['borrower_type'] ?? '');
        $borrowerRefId = (int) ($body['borrower_ref_id'] ?? 0);

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

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::reservationService()->createReservation(
            new CreateReservationRequest($bookId, $borrowerType, $borrowerRefId),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/library/reservations/{id}/cancel',
        tags: ['Reservations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Cancelled.', content: new OA\JsonContent(ref: '#/components/schemas/ReservationResponse'))],
    )]
    public function cancel(int $id)
    {
        return $this->respondSuccess(Services::reservationService()->cancelReservation($id)->toArray());
    }

    #[OA\Post(
        path: '/library/reservations/process-expired-notifications',
        tags: ['Reservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'BR-LIB-006 — every lapsed notification window is expired and, where a Waiting holder exists for the same book, the next one is notified.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'expired_count', type: 'integer'),
                        new OA\Property(property: 'promoted_count', type: 'integer'),
                        new OA\Property(property: 'expirations', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'expired_reservation_id', type: 'integer'),
                                new OA\Property(property: 'promoted_reservation_id', type: 'integer', nullable: true),
                            ],
                            type: 'object',
                        )),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function processExpiredNotifications()
    {
        return $this->respondSuccess(Services::reservationService()->processExpiredNotifications()->toArray());
    }

    #[OA\Get(
        path: '/library/reservations/{id}',
        tags: ['Reservations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ReservationResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::reservationService()->getReservation($id)->toArray());
    }

    #[OA\Get(
        path: '/library/reservations',
        tags: ['Reservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'book_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'borrower_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Student', 'Employee'])),
            new OA\Parameter(name: 'borrower_ref_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK. Filter by book_id, or by borrower_type + borrower_ref_id.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ReservationResponse')),
            ),
        ],
    )]
    public function index()
    {
        $bookId        = (int) ($this->request->getGet('book_id') ?? 0);
        $borrowerType  = (string) ($this->request->getGet('borrower_type') ?? '');
        $borrowerRefId = (int) ($this->request->getGet('borrower_ref_id') ?? 0);

        if ($bookId > 0) {
            $responses = Services::reservationService()->listByBook($bookId);

            return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
        }

        $fields = [];

        if (! in_array($borrowerType, self::VALID_BORROWER_TYPES, true)) {
            $fields['borrower_type'] = 'borrower_type query parameter must be one of Student, Employee (or supply book_id instead).';
        }

        if ($borrowerRefId <= 0) {
            $fields['borrower_ref_id'] = 'borrower_ref_id query parameter is required when borrower_type is supplied.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::reservationService()->listByBorrower($borrowerType, $borrowerRefId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
