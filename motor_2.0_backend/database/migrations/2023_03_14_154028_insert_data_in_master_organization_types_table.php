<?php

use Illuminate\Support\Facades\Schema;
use App\Models\MasterOrganizationTypes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertDataInMasterOrganizationTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        MasterOrganizationTypes::insert([
            [
                'company_alias' => 'sbi',
                'value' => 'HUF',
                'code' => '2'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Trust',
                'code' => '9'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Bank',
                'code' => '10'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Limited Liability Partnership - Domestic',
                'code' => '11'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'FPI Non Ind',
                'code' => '14'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Partnership Firm',
                'code' => '17'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'AOP',
                'code' => '18'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Society',
                'code' => '26'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Public Ltd',
                'code' => '29'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Private Ltd',
                'code' => '30'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Foreign Company',
                'code' => '35'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Government or SOE',
                'code' => '58'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Non Profit Organisation',
                'code' => '59'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Not Specified but Non-Ind',
                'code' => '60'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Sole Proprietorship',
                'code' => 'C1'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Liquidator',
                'code' => '68'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Artificial Juridical Person',
                'code' => '3'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Entity Created by Statute',
                'code' => '31'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Corporate Body',
                'code' => '6'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Entity Created by Statute',
                'code' => '31'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Charities',
                'code' => '19'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Foreign Company',
                'code' => '35'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Financial Institutions',
                'code' => '3'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Not Specified but Non-Ind',
                'code' => '60'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Mutual Fund',
                'code' => '8'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Government or State Owned Entity',
                'code' => '58'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Unincorporated',
                'code' => '43'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Chit Fund',
                'code' => '72'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Cooperative Banks',
                'code' => '73'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Foreign Banks',
                'code' => '74'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Non Scheduled Commercial Banks',
                'code' => '75'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Non Scheduled Cooperative Banks',
                'code' => '76'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Overseas Corporate Bodies',
                'code' => '77'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Private Sector Bank',
                'code' => '78'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Regional Rural Banks',
                'code' => '79'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Scheduled Commercial Banks',
                'code' => '80'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Scheduled Cooperative Banks',
                'code' => '81'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Section 25 Company',
                'code' => '82'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Venture Capital Fund',
                'code' => '83'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Welfare Funds',
                'code' => '84'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Joint Sector',
                'code' => '85'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Joint Venture',
                'code' => '86'
            ],
            [
                'company_alias' => 'sbi',
                'value' => 'Artifical Liability Partnership',
                'code' => '88'
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_organization_types', function (Blueprint $table) {
            //
        });
    }
}
