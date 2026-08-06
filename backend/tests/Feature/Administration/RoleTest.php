<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\RoleModel;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * @internal
 */
final class RoleTest extends AdministrationTestCase
{
    public function testCreateRoleAndReadItBack(): void
    {
        $user   = $this->createUser();
        $tokens = $this->loginAs($user['username']);

        $create = $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->withBodyFormat('json')
            ->post('api/v1/administration/roles', [
                'role_name'      => 'Finance Team',
                'description'    => 'Fee/payments',
                'permission_set' => ['read', 'create'],
            ]);

        $create->assertStatus(201);
        $body = $this->decode($create);
        $this->assertSame(['read', 'create'], $body['data']['permission_set']);

        $show = $this->withHeaders($this->authHeaders($tokens['access_token']))
            ->get('api/v1/administration/roles/' . $body['data']['role_id']);

        $show->assertStatus(200);
        $this->assertSame('Finance Team', $this->decode($show)['data']['role_name']);
    }

    public function testDuplicateRoleNameIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/roles', [
            'role_name'      => 'Duplicate Name',
            'permission_set' => ['read'],
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/roles', [
                'role_name'      => 'Duplicate Name',
                'permission_set' => ['read'],
            ]),
            BusinessRuleException::class,
            'ROLE_NAME_ALREADY_TAKEN',
            422,
        );
    }

    public function testCreatingARoleWritesAnAuditLogEntry(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/roles', [
            'role_name'      => 'Audited Role',
            'permission_set' => ['read'],
        ]);

        $roleId = $this->decode($create)['data']['role_id'];

        $history = $this->withHeaders($headers)->get("api/v1/administration/audit-logs/by-entity/Role/{$roleId}");

        $history->assertStatus(200);
        $rows = $this->decode($history)['data'];

        $this->assertCount(1, $rows);
        $this->assertSame('CREATE', $rows[0]['action']);
        $this->assertSame($user['id'], $rows[0]['performed_by']);
        $this->assertNull($rows[0]['old_value']);
        $this->assertSame('Audited Role', $rows[0]['new_value']['role_name']);
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/roles', [
            'role_name'      => 'Protected Role',
            'permission_set' => ['read'],
        ]);
        $roleId = $this->decode($create)['data']['role_id'];

        (new RoleModel())->update($roleId, ['is_system_role' => true]);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->delete('api/v1/administration/roles/' . $roleId),
            BusinessRuleException::class,
            'ROLE_IS_SYSTEM_PROTECTED',
            422,
        );
    }
}
