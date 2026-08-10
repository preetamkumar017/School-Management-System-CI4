<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * ENT-SYS-008. SchoolProfile entity mapping.
 *
 * @property int|null $school_id
 * @property string   $school_name
 * @property string   $short_name
 * @property string|null $school_code
 * @property string   $address_line1
 * @property string   $address_line2
 * @property string   $city
 * @property string   $state
 * @property string|null $district
 * @property string|null $block
 * @property string   $pin_code
 * @property string   $country
 * @property string   $school_type
 * @property array    $school_levels_offered
 * @property string   $management_type
 * @property string   $medium_of_instruction
 * @property string   $residential_status
 * @property string   $board_affiliation_ref
 * @property string|null $board_affiliation_number
 * @property string|null $recognition_number
 * @property string|null $affiliation_validity_start
 * @property string|null $affiliation_validity_end
 * @property string|null $udise_code
 * @property string|null $state_board_code
 * @property int|null $principal_employee_id
 * @property string|null $principal_name
 * @property string|null $principal_email
 * @property string|null $principal_phone
 * @property string   $school_email
 * @property string   $school_phone
 * @property string|null $emergency_contact
 * @property int|null $primary_logo_id
 * @property int|null $document_logo_id
 * @property string|null $document_header_text
 * @property string|null $document_footer_text
 */
class SchoolProfile extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'school_id'             => 'integer',
            'school_levels_offered' => 'json-array',
            'principal_employee_id' => '?integer',
            'primary_logo_id'       => '?integer',
            'document_logo_id'      => '?integer',
        ]);

        parent::__construct($data);
    }
}
