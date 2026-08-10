<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateClassRequest;
use App\Modules\Academic\DTOs\UpdateClassRequest;
use Config\Services;

class ClassController extends BaseController
{
    public function create()
    {
        [$className, $sequenceOrder] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::classService()->createClass(new CreateClassRequest($className, $sequenceOrder));

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        [$className, $sequenceOrder] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::classService()->updateClass($id, new UpdateClassRequest($className, $sequenceOrder));

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::classService()->deleteClass($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::classService()->getClass($id)->toArray());
    }

    public function index()
    {
        $responses = Services::classService()->listClasses();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int}
     */
    private function validateFields(array $body): array
    {
        $className = trim((string) ($body['class_name'] ?? ''));
        $fields    = [];

        if ($className === '' || strlen($className) > 20) {
            $fields['class_name'] = 'class_name is required and must be at most 20 characters.';
        }

        if (! isset($body['sequence_order']) || ! is_numeric($body['sequence_order'])) {
            $fields['sequence_order'] = 'sequence_order is required and must be an integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$className, (int) $body['sequence_order']];
    }
}
