<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class ClassTest extends AcademicTestCase
{
    public function testCreateUpdateAndListClasses(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/classes', [
            'class_name'     => 'Class 6',
            'sequence_order' => 600001,
        ]);
        $create->assertStatus(201);
        $classId = $this->decode($create)['data']['class_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch('api/v1/academic/classes/' . $classId, [
            'class_name'     => 'Class 6A',
            'sequence_order' => 600001,
        ]);
        $update->assertStatus(200);
        $this->assertSame('Class 6A', $this->decode($update)['data']['class_name']);

        $list = $this->withHeaders($headers)->get('api/v1/academic/classes');
        $list->assertStatus(200);
        $this->assertNotEmpty($this->decode($list)['data']);
    }

    public function testDuplicateClassNameIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/classes', [
            'class_name'     => 'Duplicate Class',
            'sequence_order' => 700001,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/classes', [
                'class_name'     => 'Duplicate Class',
                'sequence_order' => 700002,
            ]),
            BusinessRuleException::class,
            'CLASS_NAME_ALREADY_TAKEN',
            422,
        );
    }

    public function testDuplicateSequenceOrderIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/classes', [
            'class_name'     => 'Class A',
            'sequence_order' => 800001,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/classes', [
                'class_name'     => 'Class B',
                'sequence_order' => 800001,
            ]),
            BusinessRuleException::class,
            'CLASS_SEQUENCE_ORDER_ALREADY_TAKEN',
            422,
        );
    }
}
