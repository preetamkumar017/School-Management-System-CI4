<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateGradingSchemeRequest;
use App\Modules\Academic\DTOs\GradingSchemeResponse;
use App\Modules\Academic\DTOs\UpdateGradingSchemeRequest;
use App\Modules\Academic\Entities\GradingScheme;
use App\Modules\Academic\Models\GradingSchemeModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 */
class GradingSchemeService
{
    public function __construct(
        private readonly GradingSchemeModel $gradingSchemeModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createGradingScheme(CreateGradingSchemeRequest $request): GradingSchemeResponse
    {
        if ($this->gradingSchemeModel->existsBySchemeName($request->schemeName)) {
            throw new BusinessRuleException(
                'GRADING_SCHEME_NAME_ALREADY_TAKEN',
                'This grading scheme name is already taken.',
            );
        }

        $this->assertGradeBandsValid($request->gradeBandJson);

        $id = $this->gradingSchemeModel->insert([
            'scheme_name'     => $request->schemeName,
            'board_type'      => $request->boardType,
            'grade_band_json' => $request->gradeBandJson,
        ], true);

        $scheme = $this->gradingSchemeModel->find($id);

        $this->auditService->record('GradingScheme', $id, AuditLog::ACTION_CREATE, null, $scheme->toRawArray());

        return new GradingSchemeResponse($scheme);
    }

    /**
     * Phase 4's decided behavior: mutate in place if unreferenced by a
     * closed Exam, otherwise reject — the caller creates a new
     * GradingScheme (a new scheme_name) instead. No versioning mechanism.
     */
    public function updateGradingScheme(int $id, UpdateGradingSchemeRequest $request): GradingSchemeResponse
    {
        $before = $this->requireGradingScheme($id);

        if ($this->gradingSchemeModel->isReferencedByClosedExam($id)) {
            throw new BusinessRuleException(
                'GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM',
                'This grading scheme is referenced by a closed exam and can no longer be changed. '
                    . 'Create a new grading scheme instead.',
            );
        }

        $this->assertGradeBandsValid($request->gradeBandJson);

        $this->gradingSchemeModel->update($id, [
            'board_type'      => $request->boardType,
            'grade_band_json' => $request->gradeBandJson,
        ]);

        $after = $this->gradingSchemeModel->find($id);

        $this->auditService->record(
            'GradingScheme',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new GradingSchemeResponse($after);
    }

    public function getGradingScheme(int $id): GradingSchemeResponse
    {
        return new GradingSchemeResponse($this->requireGradingScheme($id));
    }

    /**
     * @return list<GradingSchemeResponse>
     */
    public function listGradingSchemes(): array
    {
        return array_map(
            static fn (GradingScheme $scheme): GradingSchemeResponse => new GradingSchemeResponse($scheme),
            $this->gradingSchemeModel->findAll(),
        );
    }

    private function requireGradingScheme(int $id): GradingScheme
    {
        $scheme = $this->gradingSchemeModel->find($id);

        if ($scheme === null) {
            throw new BusinessRuleException('GRADING_SCHEME_NOT_FOUND', 'Grading scheme not found.');
        }

        return $scheme;
    }

    /**
     * Structural validity of grade_band_json (Phase 1 §4.8, Phase 3):
     * every band value must be a "low-high" numeric range, and the full
     * set of bands must be non-overlapping. Declaration order in the
     * request isn't assumed to already be ascending — this checks the
     * resulting set of ranges is non-overlapping once sorted, which is
     * the property BR-EXM-007 actually needs.
     *
     * @param array<string, mixed> $gradeBandJson
     */
    private function assertGradeBandsValid(array $gradeBandJson): void
    {
        if ($gradeBandJson === []) {
            throw new ValidationException(
                ['grade_band_json' => 'At least one grade band is required.'],
            );
        }

        $ranges = [];

        foreach ($gradeBandJson as $grade => $band) {
            if (! is_string($band) || preg_match('/^(\d+)-(\d+)$/', $band, $matches) !== 1) {
                throw new ValidationException(
                    ['grade_band_json' => "Grade band \"{$grade}\" must be a \"low-high\" numeric range."],
                );
            }

            $low  = (int) $matches[1];
            $high = (int) $matches[2];

            if ($low > $high) {
                throw new ValidationException(
                    ['grade_band_json' => "Grade band \"{$grade}\" has a low bound greater than its high bound."],
                );
            }

            $ranges[] = [$low, $high];
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        for ($i = 1; $i < count($ranges); $i++) {
            if ($ranges[$i][0] <= $ranges[$i - 1][1]) {
                throw new ValidationException(
                    ['grade_band_json' => 'Grade bands must be non-overlapping.'],
                );
            }
        }
    }
}
