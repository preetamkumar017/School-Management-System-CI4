<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use App\Core\Http\RequestContext;
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Renders every exception as the standard response envelope (Company
 * Development Standard §7/§10) instead of CI4's HTML debug page — this
 * is a JSON API, every response including error responses uses the one
 * envelope shape. Registered from Config\Exceptions::handler().
 *
 * Anything not an ApiException is the System/Unhandled category: logged
 * in full here, but the caller only ever sees a generic message — no
 * stack trace, SQL text, or internal identifier, regardless of role
 * (Company Development Standard §10).
 */
class ApiExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if ($exception instanceof ApiException) {
            $category = $exception->category();
            $errorCode = $exception->errorCode();
            $message = $exception->getMessage();
            $httpStatus = $exception->httpStatus();
            $fields = $exception instanceof ValidationException ? $exception->fields() : null;
        } else {
            log_message('critical', '{exception}', ['exception' => $exception]);

            $category = 'system';
            $errorCode = 'SYSTEM_ERROR';
            $message = 'An unexpected error occurred.';
            $httpStatus = 500;
            $fields = null;
        }

        $requestId = RequestContext::requestId() ?? RequestContext::resolveRequestId($request);

        $body = [
            'success' => false,
            'data'    => null,
            'error'   => [
                'category' => $category,
                'code'     => $errorCode,
                'message'  => $message,
                'fields'   => $fields,
            ],
            'meta'    => [
                'request_id' => $requestId,
            ],
        ];

        $response
            ->setStatusCode($httpStatus)
            ->setJSON($body)
            ->send();

        exit($exitCode);
    }
}
