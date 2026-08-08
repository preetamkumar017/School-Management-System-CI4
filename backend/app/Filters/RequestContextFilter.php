<?php

declare(strict_types=1);

namespace App\Filters;

use App\Core\Http\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Runs on every request, before auth — assigns the request_id used for
 * correlation across logs, audit entries, and error responses (Company
 * Development Standard §5, §8).
 */
class RequestContextFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        RequestContext::reset();
        RequestContext::setRequestId(RequestContext::resolveRequestId($request));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
