<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class SubjectCategoryTest extends AcademicTestCase
{
    public function testSubjectCategoryCRUD(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        // Create Category
        $res = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/subject-categories', [
            'category_name' => 'Additional Opt',
            'category_code' => 'ADDNL_OPT',
            'description'   => 'Extra optional subjects',
        ]);
        $res->assertStatus(201);
        $categoryId = $this->decode($res)['data']['subject_category_id'];

        // Get Category
        $get = $this->withHeaders($headers)->get("api/v1/academic/subject-categories/{$categoryId}");
        $get->assertStatus(200);
        $this->assertSame('Additional Opt', $this->decode($get)['data']['category_name']);

        // Update Category
        $update = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/academic/subject-categories/{$categoryId}", [
            'category_name' => 'Additional Optional',
            'category_code' => 'ADDNL_OPT',
            'description'   => 'Extra optional subjects updated',
            'is_active'     => 1,
        ]);
        $update->assertStatus(200);

        // List Categories
        $list = $this->withHeaders($headers)->get('api/v1/academic/subject-categories');
        $list->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($this->decode($list)['data']));

        // Delete Category
        $this->withHeaders($headers)->delete("api/v1/academic/subject-categories/{$categoryId}")->assertStatus(204);
    }
}
