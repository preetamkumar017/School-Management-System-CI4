<?php

declare(strict_types=1);

namespace App\Core\Response;

use App\Core\Http\RequestContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Builds the standard response envelope (Company Development Standard §7).
 * Used by BaseController; every Controller method returns through one of
 * these, never a bare CI4 Response.
 */
trait ApiResponseTrait
{
    protected function respondSuccess(mixed $data = null, array $meta = [], int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => true,
                'data'    => $data,
                'error'   => null,
                'meta'    => $this->withRequestId($meta),
            ]);
    }

    protected function respondCreated(mixed $data = null, array $meta = []): ResponseInterface
    {
        return $this->respondSuccess($data, $meta, 201);
    }

    protected function respondPaginated(array $items, int $page, int $perPage, int $total): ResponseInterface
    {
        return $this->respondSuccess($items, [
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
            ],
        ]);
    }

    /**
     * @param array<string, string>|null $fields
     */
    protected function respondError(
        string $category,
        string $code,
        string $message,
        int $status,
        ?array $fields = null,
    ): ResponseInterface {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'data'    => null,
                'error'   => [
                    'category' => $category,
                    'code'     => $code,
                    'message'  => $message,
                    'fields'   => $fields,
                ],
                'meta'    => $this->withRequestId([]),
            ]);
    }

    private function withRequestId(array $meta): array
    {
        $meta['request_id'] = RequestContext::requestId();

        return $meta;
    }
}
