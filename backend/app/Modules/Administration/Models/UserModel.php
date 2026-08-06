<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\User;

/**
 * docs/design/administration/Phase-2-Model-Design.md
 */
class UserModel extends BaseModel
{
    protected $table         = 'users';
    protected $primaryKey    = 'user_id';
    protected $returnType    = User::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'username',
        'password_hash',
        'role_id',
        'owner_type',
        'owner_ref_id',
        'status',
        'failed_login_count',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    // Deliberately no $validationRules here: insert and update carry
    // different required fields (e.g. password_hash only on create), and
    // CI4 Model validation applies one rule set to both — validation
    // happens against the request DTO in the Service layer instead, where
    // create/update are already distinct types (Phase 3).

    public function findByUsername(string $username): ?User
    {
        return $this->where('username', $username)->first();
    }

    public function existsByUsername(string $username): bool
    {
        return $this->where('username', $username)->countAllResults() > 0;
    }

    public function existsByUsernameExceptId(string $username, int $id): bool
    {
        return $this->where('username', $username)->where('user_id !=', $id)->countAllResults() > 0;
    }

    public function incrementFailedLoginCount(int $id): int
    {
        $user = $this->find($id);
        $count = ($user->failed_login_count ?? 0) + 1;

        $this->skipValidation(true)->update($id, ['failed_login_count' => $count]);

        return $count;
    }

    public function resetFailedLoginCount(int $id): void
    {
        $this->skipValidation(true)->update($id, ['failed_login_count' => 0]);
    }

    public function findByOwner(string $ownerType, int $ownerRefId): ?User
    {
        return $this->where('owner_type', $ownerType)->where('owner_ref_id', $ownerRefId)->first();
    }
}
