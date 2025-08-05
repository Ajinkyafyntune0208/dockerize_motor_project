<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;

class MmvProposalJourneyBlocker extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('mmv_proposal_journey_blocker')) {
            Schema::create('mmv_proposal_journey_blocker', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('value', ['Y','N'])->default('N');
                $table->string('broker_id')->nullable()->default(null);
                $table->timestamps();
            });
        }
        DB::table('mmv_proposal_journey_blocker')->truncate();
        DB::table('mmv_proposal_journey_blocker')->insert([
            ['name' => 'Segment', 'value' => 'N'],
            ['name' => 'Make', 'value' => 'N'],
            ['name' => 'Model', 'value' => 'N'],
            ['name' => 'Fuel', 'value' => 'N']
        ]);

        $avaliableMenus = Menu::pluck('menu_slug')->toArray();
        $parent_id = Menu::where('menu_slug', 'vahan_service_configurator')->value('menu_id'); 

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'vahan journey configurations',
                'parent_id' => $parent_id,
                'menu_slug' => 'vahan_journey_configurations',
                'menu_url' => '/admin/vahan-journey-config',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

        foreach ($menus as $menu) {
            if (!in_array($menu['menu_slug'], $avaliableMenus)) 
            {   
                Menu::insert($menu);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
