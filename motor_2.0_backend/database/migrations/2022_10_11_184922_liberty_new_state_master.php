<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibertyNewStateMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('liberty_videocon_state_master');
        Schema::create('liberty_videocon_state_master', function (Blueprint $table) {
            $table->integer('num_state_cd');
            $table->string('txt_state');
            $table->string('buyer_state_name');
        });
        DB::table('liberty_videocon_state_master')->insert([
            [
                "num_state_cd" => 1,
                "txt_state" => "ANDAMAN & NICOBAR ISLANDS",
                "buyer_state_name" => "ANDAMAN & NICOBAR ISLANDS",
            ],
            [
                "num_state_cd" => 2,
                "txt_state" => "ANDHRA PRADESH",
                "buyer_state_name" => "Andhra Pradesh",
            ],
            [
                "num_state_cd" => 3,
                "txt_state" => "ARUNACHAL PRADESH",
                "buyer_state_name" => "Arunachal Pradesh",
            ],
            [
                "num_state_cd" => 4,
                "txt_state" => "ASSAM",
                "buyer_state_name" => "Assam",
            ],
            [
                "num_state_cd" => 5,
                "txt_state" => "BIHAR",
                "buyer_state_name" => "Bihar",
            ],
            [
                "num_state_cd" => 6,
                "txt_state" => "CHANDIGARH",
                "buyer_state_name" => "Chandigarh",
            ],
            [
                "num_state_cd" => 7,
                "txt_state" => "CHHATTISGARH",
                "buyer_state_name" => "CHHATTISGARH",
            ],
            [
                "num_state_cd" => 8,
                "txt_state" => "DADRA AND NAGAR HAVELI",
                "buyer_state_name" => "Dadra and Nagar Haveli",
            ],
            [
                "num_state_cd" => 9,
                "txt_state" => "DAMAN & DIU",
                "buyer_state_name" => "Daman and Diu",
            ],
            [
                "num_state_cd" => 10,
                "txt_state" => "DELHI",
                "buyer_state_name" => "Delhi",
            ],
            [
                "num_state_cd" => 11,
                "txt_state" => "GOA",
                "buyer_state_name" => "Goa",
            ],
            [
                "num_state_cd" => 12,
                "txt_state" => "GUJARAT",
                "buyer_state_name" => "Gujarat",
            ],
            [
                "num_state_cd" => 13,
                "txt_state" => "HARYANA",
                "buyer_state_name" => "Haryana",
            ],
            [
                "num_state_cd" => 14,
                "txt_state" => "HIMACHAL PRADESH",
                "buyer_state_name" => "Himachal Pradesh",
            ],
            [
                "num_state_cd" => 15,
                "txt_state" => "JAMMU AND KASHMIR",
                "buyer_state_name" => "Jammu & Kashmir",
            ],
            [
                "num_state_cd" => 16,
                "txt_state" => "JHARKHAND",
                "buyer_state_name" => "Jharkhand",
            ],
            [
                "num_state_cd" => 17,
                "txt_state" => "KARNATAKA",
                "buyer_state_name" => "Karnataka",
            ],
            [
                "num_state_cd" => 18,
                "txt_state" => "KERALA",
                "buyer_state_name" => "Kerala",
            ],
            [
                "num_state_cd" => 19,
                "txt_state" => "LAKSHADWEEP",
                "buyer_state_name" => "LAKSHADWEEP",
            ],
            [
                "num_state_cd" => 20,
                "txt_state" => "MADHYA PRADESH",
                "buyer_state_name" => "Madhya Pradesh",
            ],
            [
                "num_state_cd" => 21,
                "txt_state" => "MAHARASHTRA",
                "buyer_state_name" => "Maharashtra",
            ],
            [
                "num_state_cd" => 22,
                "txt_state" => "MANIPUR",
                "buyer_state_name" => "Manipur",
            ],
            [
                "num_state_cd" => 23,
                "txt_state" => "MEGHALAYA",
                "buyer_state_name" => "Meghalaya",
            ],
            [
                "num_state_cd" => 24,
                "txt_state" => "MIZORAM",
                "buyer_state_name" => "Mizoram",
            ],
            [
                "num_state_cd" => 25,
                "txt_state" => "NAGALAND",
                "buyer_state_name" => "Nagaland",
            ],
            [
                "num_state_cd" => 26,
                "txt_state" => "ODISHA",
                "buyer_state_name" => "Odisha",
            ],
            [
                "num_state_cd" => 27,
                "txt_state" => "PUDUCHERRY",
                "buyer_state_name" => "PUDUCHERRY",
            ],
            [
                "num_state_cd" => 28,
                "txt_state" => "PUNJAB",
                "buyer_state_name" => "Punjab",
            ],
            [
                "num_state_cd" => 29,
                "txt_state" => "RAJASTHAN",
                "buyer_state_name" => "Rajasthan",
            ],
            [
                "num_state_cd" => 30,
                "txt_state" => "SIKKIM",
                "buyer_state_name" => "Sikkim",
            ],
            [
                "num_state_cd" => 31,
                "txt_state" => "TAMIL NADU",
                "buyer_state_name" => "Tamil Nadu",
            ],
            [
                "num_state_cd" => 32,
                "txt_state" => "TELANGANA",
                "buyer_state_name" => "Telangana",
            ],
            [
                "num_state_cd" => 33,
                "txt_state" => "TRIPURA",
                "buyer_state_name" => "Tripura",
            ],
            [
                "num_state_cd" => 34,
                "txt_state" => "UTTAR PRADESH",
                "buyer_state_name" => "Uttar Pradesh",
            ],
            [
                "num_state_cd" => 35,
                "txt_state" => "UTTARAKHAND",
                "buyer_state_name" => "Uttarakhand",
            ],
            [
                "num_state_cd" => 36,
                "txt_state" => "WEST BENGAL",
                "buyer_state_name" => "West Bengal",
            ],
            [
                "num_state_cd" => 40,
                "txt_state" => "Ladakh",
                "buyer_state_name" => "",
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
        Schema::dropIfExists('liberty_videocon_state_master');
    }
}
