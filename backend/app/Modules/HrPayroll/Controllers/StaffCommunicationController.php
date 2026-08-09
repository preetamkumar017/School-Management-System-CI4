<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Modules\HrPayroll\Services\StaffCommunicationService;
use App\Modules\HrPayroll\Models\StaffCommunicationModel;
use App\Modules\HrPayroll\Models\StaffCommunicationReadModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Core\Authz\ModuleAuthorizer;
use App\Modules\Administration\Models\UserModel;

class StaffCommunicationController extends BaseController
{
    private StaffCommunicationService $service;

    public function __construct()
    {
        $this->service = new StaffCommunicationService(
            new StaffCommunicationModel(),
            new StaffCommunicationReadModel(),
            new EmployeeModel(),
            new ModuleAuthorizer(new UserModel())
        );
    }

    public function index()
    {
        return $this->respondSuccess($this->service->getCommunications());
    }

    public function unread()
    {
        $userId = request()->user->userId;
        return $this->respondSuccess($this->service->getUnreadCommunications($userId));
    }

    public function feed()
    {
        return $this->respondSuccess($this->service->getActiveFeed());
    }

    public function markRead(int $id)
    {
        $userId = request()->user->userId;
        $this->service->markAsRead($userId, $id);
        return $this->respondSuccess(['message' => 'Marked as read']);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);
        $communication = $this->service->createCommunication($data);
        return $this->respondSuccess($communication);
    }

    public function getEvents()
    {
        return $this->respondSuccess($this->service->getUpcomingEvents());
    }
}
