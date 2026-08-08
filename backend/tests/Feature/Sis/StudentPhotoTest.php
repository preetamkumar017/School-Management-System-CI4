<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Sis\SisTestCase;

/**
 * docs/ADR/ADR-023-sis-id-card-generation.md §2
 *
 * @internal
 */
final class StudentPhotoTest extends SisTestCase
{
    /**
     * A minimal valid 1x1 PNG, base64-encoded — small enough to keep the
     * fixture inline, still a genuine (decodable) image payload.
     */
    private const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function testUploadPhotoSucceedsAndSetsPhotoDocumentId(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $response = $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/photo", [
            'image_base64' => self::ONE_PIXEL_PNG_BASE64,
            'extension'    => 'png',
        ]);

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertNotNull($body['photo_document_id']);

        $show = $this->withHeaders($headers)->get("api/v1/sis/students/{$studentId}");
        $show->assertStatus(200);
        $this->assertSame($body['photo_document_id'], $this->decode($show)['data']['photo_document_id']);
    }

    public function testUploadPhotoRejectsNonImageExtension(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/photo", [
                'image_base64' => self::ONE_PIXEL_PNG_BASE64,
                'extension'    => 'pdf',
            ]),
            BusinessRuleException::class,
            'STUDENT_PHOTO_INVALID_EXTENSION',
            422,
        );
    }

    public function testUploadPhotoRejectsInvalidBase64(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/photo", [
                'image_base64' => '***not-base64***',
                'extension'    => 'png',
            ]),
            BusinessRuleException::class,
            'STUDENT_PHOTO_INVALID_DATA',
            422,
        );
    }
}
