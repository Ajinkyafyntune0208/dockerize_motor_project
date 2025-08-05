<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQuoteUrlAndProposalUrlCvJourneyStage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_journey_stages', function (Blueprint $table) {
            $table->text('quote_url')->nullable()->after('stage');
            $table->text('proposal_url')->nullable()->after('stage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cv_journey_stages', function (Blueprint $table) {
            $table->dropColumn('quote_url');
            $table->dropColumn('proposal_url');
        });
    }
}
