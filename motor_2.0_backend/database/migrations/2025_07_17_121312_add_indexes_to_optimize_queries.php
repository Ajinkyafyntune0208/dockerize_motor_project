<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AddIndexesToOptimizeQueries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!$this->indexExists('master_policy', 'master_policy_policy_id_index')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->index('policy_id');
            });
        }

        if (!$this->indexExists('master_policy', 'master_policy_insurance_company_id_index')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->index('insurance_company_id');
            });
        }

        if (!$this->indexExists('master_policy', 'master_policy_product_sub_type_id_index')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->index('product_sub_type_id');
            });
        }

        if (!$this->indexExists('master_policy', 'master_policy_premium_type_id_index')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->index('premium_type_id');
            });
        }

        if (!$this->indexExists('master_policy', 'idx_policy_join_optimized')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->index(
                    ['policy_id', 'product_sub_type_id', 'insurance_company_id', 'premium_type_id'],
                    'idx_policy_join_optimized'
                );
            });
        }

        if (!$this->indexExists('master_company', 'master_company_company_id_index')) {
            Schema::table('master_company', function (Blueprint $table) {
                $table->index('company_id');
            });
        }

        if (!$this->indexExists('master_product_sub_type', 'master_product_sub_type_product_sub_type_id_index')) {
            Schema::table('master_product_sub_type', function (Blueprint $table) {
                $table->index('product_sub_type_id');
            });
        }

        if (!$this->indexExists('master_product', 'master_product_master_policy_id_index')) {
            Schema::table('master_product', function (Blueprint $table) {
                $table->index('master_policy_id');
            });
        }


        if (!$this->indexExists('master_premium_type', 'master_premium_type_id_index')) {
            Schema::table('master_premium_type', function (Blueprint $table) {
                $table->index('id');
            });
        }

        $indexCheck = DB::select("SHOW INDEX FROM ic_version_configurators WHERE Key_name = 'idx_config_lookup'");
        if (count($indexCheck) === 0) {
            DB::statement("ALTER TABLE `ic_version_configurators`
                ADD INDEX `idx_config_lookup` (
                    `ic_alias`(50),
                    `segment`(25),
                    `business_type`(25),
                    `integration_type`(25),
                    `slug`(25)
                )");
                    }


        if (!$this->indexExists('ic_version_activations', 'idx_slug_active')) {
            Schema::table('ic_version_activations', function (Blueprint $table) {
                $table->index(['slug', 'is_active'], 'idx_slug_active');
            });
        }


        if (!$this->indexExists('user_proposal', 'user_proposal_user_proposal_id_index')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->index('user_proposal_id');
            });
        }


        if (!$this->indexExists('policy_details', 'policy_details_proposal_id_index')) {
            Schema::table('policy_details', function (Blueprint $table) {
                $table->index('proposal_id');
            });
        }


        if (!$this->indexExists('webservice_request_response_data_option_list', 'idx_company_section_method')) {
            Schema::table('webservice_request_response_data_option_list', function (Blueprint $table) {
                $table->index(['company', 'section', 'method_name'], 'idx_company_section_method');
            });
        }

        if (!$this->indexExists('quote_log', 'quote_log_user_product_journey_id_index')) {

            Schema::table('quote_log', function (Blueprint $table) {
                $table->index('user_product_journey_id');
            });
        }

        if (!$this->indexExists('quote_log', 'quote_log_ic_id_index')) {

            Schema::table('quote_log', function (Blueprint $table) {
                $table->index('ic_id');
            });
        }

        if (!$this->indexExists('quote_log', 'quote_log_master_policy_id_index')) {

            Schema::table('quote_log', function (Blueprint $table) {
                $table->index('master_policy_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}

    public function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $connection->listTableIndexes($table);
        return array_key_exists($indexName, $indexes);
    }
}
