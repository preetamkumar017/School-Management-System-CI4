<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\SchoolProfile;

class SchoolProfileModel extends BaseModel
{
    protected $table          = 'school_profiles';
    protected $primaryKey     = 'school_id';
    protected $returnType     = SchoolProfile::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'school_name',
        'short_name',
        'school_code',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'district',
        'block',
        'pin_code',
        'country',
        'school_type',
        'school_levels_offered',
        'management_type',
        'medium_of_instruction',
        'residential_status',
        'board_affiliation_ref',
        'board_affiliation_number',
        'recognition_number',
        'affiliation_validity_start',
        'affiliation_validity_end',
        'udise_code',
        'state_board_code',
        'principal_employee_id',
        'principal_name',
        'principal_email',
        'principal_phone',
        'school_email',
        'school_phone',
        'emergency_contact',
        'primary_logo_id',
        'document_logo_id',
        'document_header_text',
        'document_footer_text',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeLevels'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeLevels'];

    protected function encodeLevels(array $eventData): array
    {
        if (isset($eventData['data']['school_levels_offered']) && is_array($eventData['data']['school_levels_offered'])) {
            $eventData['data']['school_levels_offered'] = json_encode($eventData['data']['school_levels_offered']);
        }
        return $eventData;
    }
}
