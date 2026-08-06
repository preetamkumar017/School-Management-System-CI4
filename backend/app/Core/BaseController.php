<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Response\ApiResponseTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Every product Controller extends this, never CodeIgniter\Controller
 * directly (Company Development Standard §1.1). Controllers stay thin:
 * parse the request, call exactly one Service method, return through
 * ApiResponseTrait. No business logic here or in any subclass.
 */
abstract class BaseController extends \CodeIgniter\Controller
{
    use ApiResponseTrait;

    /**
     * @var list<string>
     */
    protected $helpers = [];

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);
    }
}
