<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\SchoolProfileModel;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * Feature test for School Profile & Branding.
 *
 * @internal
 */
final class SchoolProfileTest extends AdministrationTestCase
{
    public function testGetProfileInitiallyEmpty(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->get('api/v1/administration/school-profile');
        $response->assertStatus(200);
        $body = $this->decode($response);
        $this->assertEmpty($body['data']);
    }

    public function testSaveProfileValidationFailures(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        // Missing required fields
        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
                'school_name' => '',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    public function testSaveProfileSuccessfullyWithLogos(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $logoData = base64_encode('fake-logo-bytes');

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
            'school_name'                => 'Indian Public School',
            'short_name'                 => 'IPS',
            'school_code'                => 'SCH001',
            'address_line1'              => '123, Main Street',
            'address_line2'              => 'Sector 4',
            'city'                       => 'New Delhi',
            'state'                      => 'Delhi',
            'district'                   => 'New Delhi',
            'block'                      => 'Connaught Place',
            'pin_code'                   => '110001',
            'country'                    => 'India',
            'school_type'                => 'Co-educational',
            'school_levels_offered'      => ['Primary', 'Secondary'],
            'management_type'            => 'Private Unaided',
            'medium_of_instruction'      => 'English',
            'residential_status'         => 'Day School',
            'board_affiliation_ref'      => 'CBSE',
            'board_affiliation_number'   => '1234567',
            'recognition_number'         => 'REC-789',
            'affiliation_validity_start' => '2026-04-01',
            'affiliation_validity_end'   => '2030-03-31',
            'udise_code'                 => '12345678901',
            'state_board_code'           => 'STB-112',
            'school_email'               => 'info@ips.edu.in',
            'school_phone'               => '011-1234567',
            'emergency_contact'          => '9999999999',
            'primary_logo_base64'        => $logoData,
            'primary_logo_extension'     => 'png',
            'document_logo_base64'       => $logoData,
            'document_logo_extension'    => 'jpg',
            'document_header_text'       => 'INDIAN PUBLIC SCHOOL - HEAD',
            'document_footer_text'       => 'INDIAN PUBLIC SCHOOL - FOOT',
        ]);

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        $this->assertSame('Indian Public School', $body['school_name']);
        $this->assertSame('IPS', $body['short_name']);
        $this->assertSame('110001', $body['pin_code']);
        $this->assertCount(2, $body['school_levels_offered']);
        $this->assertSame('CBSE', $body['board_affiliation_ref']);
        $this->assertNotNull($body['primary_logo_id']);
        $this->assertNotNull($body['document_logo_id']);

        // Check view endpoint
        $viewResponse = $this->withHeaders($headers)->get('api/v1/administration/school-profile');
        $viewResponse->assertStatus(200);
        $viewBody = $this->decode($viewResponse)['data'];
        $this->assertSame('Indian Public School', $viewBody['school_name']);
        $this->assertStringContainsString('documents/SchoolProfile', $viewBody['primary_logo_path']);
    }

    public function testInvalidPINFormat(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
                'school_name'                => 'School',
                'short_name'                 => 'SCH',
                'address_line1'              => 'Add1',
                'address_line2'              => 'Add2',
                'city'                       => 'City',
                'state'                      => 'State',
                'pin_code'                   => '11000', // 5 digits
                'country'                    => 'India',
                'school_type'                => 'Boys',
                'school_levels_offered'      => ['Primary'],
                'management_type'            => 'Govt',
                'medium_of_instruction'      => 'Hindi',
                'residential_status'         => 'Day',
                'board_affiliation_ref'      => 'State Board',
                'school_email'               => 'sch@test.com',
                'school_phone'               => '12345678',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    public function testInvalidGeographicHierarchy(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        // Mumbai district is in Maharashtra, but state is Delhi -> should reject
        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
                'school_name'                => 'School',
                'short_name'                 => 'SCH',
                'address_line1'              => 'Add1',
                'address_line2'              => 'Add2',
                'city'                       => 'City',
                'state'                      => 'Delhi',
                'district'                   => 'Mumbai',
                'pin_code'                   => '110001',
                'country'                    => 'India',
                'school_type'                => 'Boys',
                'school_levels_offered'      => ['Primary'],
                'management_type'            => 'Govt',
                'medium_of_instruction'      => 'Hindi',
                'residential_status'         => 'Day',
                'board_affiliation_ref'      => 'State Board',
                'school_email'               => 'sch@test.com',
                'school_phone'               => '12345678',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    public function testGeographicLookupEndpoints(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        // 1. Get States
        $res = $this->withHeaders($headers)->get('api/v1/administration/states');
        $res->assertStatus(200);
        $body = $this->decode($res)['data'];
        $this->assertNotEmpty($body);
        $stateId = $body[0]['state_id'];

        // 2. Get Districts
        $res = $this->withHeaders($headers)->get("api/v1/administration/states/{$stateId}/districts");
        $res->assertStatus(200);
        $bodyDistricts = $this->decode($res)['data'];
        $this->assertNotEmpty($bodyDistricts);
        $districtId = $bodyDistricts[0]['district_id'];

        // 3. Get Blocks
        $res = $this->withHeaders($headers)->get("api/v1/administration/districts/{$districtId}/blocks");
        $res->assertStatus(200);
        $bodyBlocks = $this->decode($res)['data'];
        $this->assertNotEmpty($bodyBlocks);
    }

    public function testChhattisgarhGeographicHierarchy(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        // 1. Verify Chhattisgarh is in states list
        $res = $this->withHeaders($headers)->get('api/v1/administration/states');
        $res->assertStatus(200);
        $states = $this->decode($res)['data'];
        
        $cgState = null;
        foreach ($states as $s) {
            if ($s['name'] === 'Chhattisgarh') {
                $cgState = $s;
                break;
            }
        }
        $this->assertNotNull($cgState, 'Chhattisgarh should be present in states list.');
        $cgStateId = $cgState['state_id'];

        // 2. Verify Chhattisgarh districts
        $res = $this->withHeaders($headers)->get("api/v1/administration/states/{$cgStateId}/districts");
        $res->assertStatus(200);
        $districts = $this->decode($res)['data'];
        $this->assertCount(3, $districts);
        
        $districtNames = array_map(fn($d) => $d['name'], $districts);
        $this->assertContains('Raipur', $districtNames);
        $this->assertContains('Sakti', $districtNames);
        $this->assertContains('Sarangarh-Bilaigarh', $districtNames);

        // Find Raipur and Sakti and Sarangarh-Bilaigarh district IDs
        $raipurId = null;
        $saktiId = null;
        $sarangarhId = null;
        foreach ($districts as $d) {
            if ($d['name'] === 'Raipur') $raipurId = $d['district_id'];
            if ($d['name'] === 'Sakti') $saktiId = $d['district_id'];
            if ($d['name'] === 'Sarangarh-Bilaigarh') $sarangarhId = $d['district_id'];
        }

        // 3. Verify Raipur blocks
        $res = $this->withHeaders($headers)->get("api/v1/administration/districts/{$raipurId}/blocks");
        $res->assertStatus(200);
        $raipurBlocks = array_map(fn($b) => $b['name'], $this->decode($res)['data']);
        $this->assertCount(4, $raipurBlocks);
        $this->assertContains('Dharsiva', $raipurBlocks);
        $this->assertContains('Tilda', $raipurBlocks);
        $this->assertContains('Arang', $raipurBlocks);
        $this->assertContains('Abhanpur', $raipurBlocks);

        // 4. Verify Sakti blocks
        $res = $this->withHeaders($headers)->get("api/v1/administration/districts/{$saktiId}/blocks");
        $res->assertStatus(200);
        $saktiBlocks = array_map(fn($b) => $b['name'], $this->decode($res)['data']);
        $this->assertCount(4, $saktiBlocks);
        $this->assertContains('Sakti', $saktiBlocks);
        $this->assertContains('Malkharoda', $saktiBlocks);
        $this->assertContains('Jaijaipur', $saktiBlocks);
        $this->assertContains('Dabhra', $saktiBlocks);

        // 5. Verify Sarangarh-Bilaigarh blocks
        $res = $this->withHeaders($headers)->get("api/v1/administration/districts/{$sarangarhId}/blocks");
        $res->assertStatus(200);
        $sbBlocks = array_map(fn($b) => $b['name'], $this->decode($res)['data']);
        $this->assertCount(3, $sbBlocks);
        $this->assertContains('Sarangarh', $sbBlocks);
        $this->assertContains('Bilaigarh', $sbBlocks);
        $this->assertContains('Baramkela', $sbBlocks);

        // 6. Post valid School Profile using Chhattisgarh -> Sakti -> Malkharoda
        $logoData = base64_encode('fake-logo-bytes');
        $res = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
            'school_name'                => 'Chhattisgarh Model School',
            'short_name'                 => 'CMS',
            'address_line1'              => 'Block A, Raipur Road',
            'address_line2'              => 'Near Station',
            'city'                       => 'Raipur',
            'state'                      => 'Chhattisgarh',
            'district'                   => 'Sakti',
            'block'                      => 'Malkharoda',
            'pin_code'                   => '495689',
            'country'                    => 'India',
            'school_type'                => 'Co-educational',
            'school_levels_offered'      => ['Primary'],
            'management_type'            => 'Private Unaided',
            'medium_of_instruction'      => 'English',
            'residential_status'         => 'Day School',
            'board_affiliation_ref'      => 'CBSE',
            'school_email'               => 'cms@cg.gov.in',
            'school_phone'               => '0771-123456',
            'primary_logo_base64'        => $logoData,
            'primary_logo_extension'     => 'png',
            'document_logo_base64'       => $logoData,
            'document_logo_extension'    => 'jpg',
        ]);
        $res->assertStatus(200);

        // 7. Post invalid combination: Raipur district but Malkharoda block (which belongs to Sakti)
        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/administration/school-profile', [
                'school_name'                => 'CG School Fail',
                'short_name'                 => 'CGSF',
                'address_line1'              => 'Block A',
                'address_line2'              => 'Near Station',
                'city'                       => 'Raipur',
                'state'                      => 'Chhattisgarh',
                'district'                   => 'Raipur',
                'block'                      => 'Malkharoda', // invalid for Raipur
                'pin_code'                   => '495689',
                'country'                    => 'India',
                'school_type'                => 'Co-educational',
                'school_levels_offered'      => ['Primary'],
                'management_type'            => 'Private Unaided',
                'medium_of_instruction'      => 'English',
                'residential_status'         => 'Day School',
                'board_affiliation_ref'      => 'CBSE',
                'school_email'               => 'cgsf@cg.gov.in',
                'school_phone'               => '0771-123456',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }
}

