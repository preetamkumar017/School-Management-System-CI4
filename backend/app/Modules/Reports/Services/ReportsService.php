<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\DTOs\SummaryResponse;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/reports/Phase-1-Service-Controller-Design.md
 * Pure composition over other modules' Services, never their Models
 * (Company Development Standard's cross-module rule) — no entity, no
 * Model of its own (ADR-010 §7).
 */
class ReportsService
{
    public function getSummary(): SummaryResponse
    {
        $books = AppServices::bookService()->listBooks();

        return new SummaryResponse(
            Time::now()->toDateTimeString(),
            count(AppServices::userService()->listUsers()),
            count(AppServices::classService()->listClasses()),
            count(AppServices::academicSessionService()->listSessions()),
            count(AppServices::departmentService()->listDepartments()),
            count(AppServices::designationService()->listDesignations()),
            count(AppServices::employeeService()->listEmployees()),
            count($books),
            count(array_filter($books, static fn ($book): bool => $book->isAvailable)),
            count(AppServices::vehicleService()->listVehicles()),
            count(AppServices::routeService()->listRoutes()),
            count(AppServices::feeHeadService()->listFeeHeads()),
        );
    }
}
