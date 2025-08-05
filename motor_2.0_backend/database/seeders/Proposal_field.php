<?php

namespace Database\Seeders;

use App\Models\ProposalFields;
use Illuminate\Database\Seeder;

class Proposal_field extends Seeder
{
    /**
     * Run the database seeds.
     * @return void
     */
    public function run()
    {
        $company_alias = ['acko', 'bajaj_allianz', 'bharti_axa', 'cholla_mandalam', 'edelweiss', 'future_generali', 'godigit', 'hdfc_ergo', 'icici_lombard', 'iffco_tokio', 'kotak', 'liberty_videocon', 'magma', 'new_india', 'oriental', 'raheja', 'reliance', 'royal_sundaram', 'sbi', 'shriram', 'tata_aig', 'united_india', 'universal_sompo'];
        $sections = ['bike', 'cv', 'car'];
        $owner_types = ['C', 'I'];
        $fields1 = '["gstNumber","maritalStatus","occupation","panNumber","dob","gender","vehicleColor","hypothecationCity",0,0]';
        $fields2 = ' ["gstNumber",null,null,"panNumber",null,null,"vehicleColor","hypothecationCity",0,0]';
        foreach ($company_alias as $row) {
            foreach ($sections as $section) {
                foreach ($owner_types as $owner_type) {
                    if ($owner_type == 'I') {
                        $fields = $fields1;
                    }
                    if ($owner_type == 'C') {
                        $fields = $fields2;
                    }
                    ProposalFields::firstOrCreate([
                        'company_alias' => $row,
                        'section' => $section,
                        'owner_type' => $owner_type,
                    ], [
                        'fields' => $fields,
                        'company_alias' => $row,
                        'section' => $section,
                        'owner_type' => $owner_type,
                    ]);
                }

            }

        }
    }
}
