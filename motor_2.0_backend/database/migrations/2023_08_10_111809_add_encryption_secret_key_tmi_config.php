<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddEncryptionSecretKeyTmiConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            "0" => [
                "TMI_PUSH_DATA_SECRET_KEY",
                "TMI_PUSH_DATA_SECRET_KEY",
                null,
            ],
            "1" => [
                "TMI_PUSH_DATA_SECRET_IV",
                "TMI_PUSH_DATA_SECRET_KEY",
                null,
            ],
        ];

        foreach ($data as $dat) {
            DB::table('config_settings')->updateOrInsert([
                'label' => $dat[0],
                'key' => $dat[1],
                'value' => $dat[2],
            ],
            [
                'environment' => 'local',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('config_settings')->updateOrInsert([
            'label' => 'TMI_PUSH_DATA_ENABLE',
            'key' => 'TMI_PUSH_DATA_ENABLE',
            'value' => 'N',
        ],
        [
            'environment' => 'local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
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
