<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateSectionRequest;
use App\Modules\Academic\DTOs\UpdateSectionRequest;
use Config\Services;

class SectionController extends BaseController
{
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $classId = (int) ($body['class_id'] ?? 0);
        [$sectionName, $capacity] = $this->validateFields($body);

        if ($classId <= 0) {
            throw new ValidationException(['class_id' => 'class_id is required.']);
        }

        $response = Services::sectionService()->createSection(
            new CreateSectionRequest($classId, $sectionName, $capacity),
        );

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        [$sectionName, $capacity] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::sectionService()->updateSection($id, new UpdateSectionRequest($sectionName, $capacity));

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::sectionService()->deleteSection($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::sectionService()->getSection($id)->toArray());
    }

    public function index()
    {
        $classId = (int) ($this->request->getGet('class_id') ?? 0);

        if ($classId <= 0) {
            throw new ValidationException(['class_id' => 'class_id query parameter is required.']);
        }

        $responses = Services::sectionService()->listSectionsByClass($classId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int}
     */
    private function validateFields(array $body): array
    {
        $sectionName = trim((string) ($body['section_name'] ?? ''));
        $fields      = [];

        if ($sectionName === '' || strlen($sectionName) > 10) {
            $fields['section_name'] = 'section_name is required and must be at most 10 characters.';
        }

        if (! isset($body['capacity']) || ! is_numeric($body['capacity']) || (int) $body['capacity'] <= 0) {
            $fields['capacity'] = 'capacity is required and must be a positive integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$sectionName, (int) $body['capacity']];
    }
}
