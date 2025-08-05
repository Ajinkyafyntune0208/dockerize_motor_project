<?php

namespace Database\Seeders;

use App\Models\MasterOrganizationTypes;
use Illuminate\Database\Seeder;

class HdfcErgoOrganizationTypes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MasterOrganizationTypes::insert([
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Company',
                'code' => 'company'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Partnership Firm',
                'code' => 'partnershipFirm'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Trust',
                'code' => 'trust'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Unincorporated Institution',
                'code' => 'unincorporatedInstitution'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Properietor',
                'code' => 'properietor'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'HUF',
                'code' => 'huf'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'LLP',
                'code' => 'llp'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Society or Educational Institute',
                'code' => 'societyOrEducationalInstitute'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Government Entity',
                'code' => 'governmentEntity'
            ],
            [
                'company_alias' => 'hdfc_ergo',
                'value' => 'Foreign Embassy',
                'code' => 'foreignEmbassy'
            ]
        ]);
    }
}
