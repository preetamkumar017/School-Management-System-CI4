<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Modules\Administration\Entities\RefreshToken;
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * docs/design/administration/Phase-2-Model-Design.md
 * Extends CodeIgniter\Model directly, not App\Core\BaseModel — no
 * updated_by/soft-delete columns exist on this table.
 */
class RefreshTokenModel extends Model
{
    protected $table         = 'refresh_tokens';
    protected $primaryKey    = 'refresh_token_id';
    protected $returnType    = RefreshToken::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    public function findValidByTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->where('expires_at >', Time::now()->toDateTimeString())
            ->first();
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)
            ->where('revoked_at', null)
            ->set(['revoked_at' => Time::now()->toDateTimeString()])
            ->update();
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        $this->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->set(['revoked_at' => Time::now()->toDateTimeString()])
            ->update();
    }
}
