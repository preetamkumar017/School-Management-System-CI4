<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use Config\Services;
use Tests\Support\Sis\SisTestCase;

/**
 * docs/ADR/ADR-023-sis-id-card-generation.md §3/§4
 *
 * @internal
 */
final class StudentIdCardTest extends SisTestCase
{
    private const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function testGenerateIdCardWithoutPhotoProducesValidPdf(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sectionId = $this->createSection($this->createClassFixture('Class 5'), 'B');
        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE');

        $response = $this->withHeaders($headers)->get("api/v1/sis/students/{$studentId}/id-card");
        $response->assertStatus(201);

        $body = $this->decode($response)['data'];
        $this->assertSame('Student', $body['owner_type']);
        $this->assertSame($studentId, $body['owner_ref_id']);
        $this->assertSame('IdCard', $body['document_type']);

        $absolutePath = Services::documentService()->getAbsolutePath($body['document_id']);
        $this->assertFileExists($absolutePath);
        $contents = (string) file_get_contents($absolutePath);
        $this->assertStringStartsWith('%PDF', $contents);
    }

    /**
     * ADR-023 §3 proxy for "the photo path was actually used": the
     * generated PDF's byte size grows meaningfully once a real
     * base64-embedded photo data: URI is inlined into the HTML, versus
     * the small placeholder-box markup used when no photo is uploaded.
     */
    public function testGenerateIdCardWithPhotoEmbedsPhotoAndIsLargerThanWithoutPhoto(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        $withoutPhoto = $this->withHeaders($headers)->get("api/v1/sis/students/{$studentId}/id-card");
        $withoutPhoto->assertStatus(201);
        $withoutPhotoPath = Services::documentService()->getAbsolutePath($this->decode($withoutPhoto)['data']['document_id']);
        $withoutPhotoSize = strlen((string) file_get_contents($withoutPhotoPath));

        $this->withHeaders($headers)->post("api/v1/sis/students/{$studentId}/photo", [
            'image_base64' => self::ONE_PIXEL_PNG_BASE64,
            'extension'    => 'png',
        ])->assertStatus(200);

        $withPhoto = $this->withHeaders($headers)->get("api/v1/sis/students/{$studentId}/id-card");
        $withPhoto->assertStatus(201);
        $body = $this->decode($withPhoto)['data'];
        $this->assertSame('IdCard', $body['document_type']);

        $withPhotoPath = Services::documentService()->getAbsolutePath($body['document_id']);
        $contents      = (string) file_get_contents($withPhotoPath);
        $this->assertStringStartsWith('%PDF', $contents);

        // The base64 data: URI of the uploaded image is inlined directly
        // into the source HTML dompdf renders, so a PDF built with a real
        // photo is reliably larger than one built with the small
        // placeholder-box markup.
        $this->assertGreaterThan($withoutPhotoSize, strlen($contents));
    }
}
