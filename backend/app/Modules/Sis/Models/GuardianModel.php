<?php

declare(strict_types=1);

namespace App\Modules\Sis\Models;

use App\Core\BaseModel;
use App\Modules\Sis\Entities\Guardian;

/**
 * docs/design/sis/Phase-4.3-Repository-Design.md
 */
class GuardianModel extends BaseModel
{
    protected $table          = 'guardians';
    protected $primaryKey     = 'guardian_id';
    protected $returnType     = Guardian::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'full_name',
        'relationship',
        'mobile_number',
        'email',
        'created_by',
        'updated_by',
    ];

    /**
     * Returns a list, not a single record — mobile_number is explicitly
     * not enforced-unique (Appendix-G's Unique Constraints for Guardian).
     *
     * @return list<Guardian>
     */
    public function findByMobileNumber(string $value): array
    {
        return $this->where('mobile_number', $value)->findAll();
    }
}
