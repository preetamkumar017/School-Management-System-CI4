<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

/**
 * docs/ADR/ADR-023-sis-id-card-generation.md §2. imageBase64 (not a
 * multipart file) — this codebase's entire API surface is JSON-only
 * (every existing Controller reads getJSON(true), none use
 * IncomingRequest::getFile()); a base64 field keeps this endpoint
 * consistent with that convention instead of introducing the only
 * multipart/form-data endpoint in the codebase.
 */
final class UploadStudentPhotoRequest
{
    public function __construct(
        public readonly string $imageBase64,
        public readonly string $extension,
    ) {
    }
}
