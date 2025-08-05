<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexFormTablels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         // Add index to master_product_sub_type on parent_id
        $this->addIndexIfNotExists('master_product_sub_type', 'idx_mpst_parent_id', ['parent_id']);

        // Add index to vahan_service_list on (status, id)
        $this->addIndexIfNotExists('vahan_service_list', 'idx_vsl_status_id', ['status', 'id']);

        // Add index to vahan_service_priority_list on (vahan_service_id, priority_no)
        $this->addIndexIfNotExists('vahan_service_priority_list', 'idx_vspl_serviceid_priority', ['vahan_service_id', 'priority_no']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }

    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes($table);

        if (!array_key_exists($indexName, $indexes)) {
            Schema::table($table, function ($table) use ($indexName, $columns) {
                $table->index($columns, $indexName);
            });
        }
    }
}
