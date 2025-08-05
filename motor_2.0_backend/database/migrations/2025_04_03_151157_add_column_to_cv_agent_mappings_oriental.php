<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToCvAgentMappingsOriental extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->json('relation_oriental')->after('relation_united_india')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->dropColumn('relation_oriental');
        });
    }
}
