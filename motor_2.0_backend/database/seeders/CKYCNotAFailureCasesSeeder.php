<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CKYCNotAFailureCasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('ckyc_not_a_failure_cases')->truncate();
        DB::table('ckyc_not_a_failure_cases')->insert(
            [
                [
                    "type" => "ckyc",
                    "message" => "No Data Found !!!",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "No Record",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "No record found",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "No record found, please retry with alternate KYC options.",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "No record exist.",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "CKYC verification failed. Redirection link found",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "CKYC Not verified / Record not found",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "POI failed",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "POI success",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "Access token generated",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "OTP Sent Successfully",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "File Upload successfully at both place",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "Please complete KYC Journey on given Link",
                    "active" => 1,
                ],
                [
                    "type" => "ckyc",
                    "message" => "Your CKYC is pending for further processing and approval, you are kindly requested to wait for some time and try again",
                    "active" => 1,
                ],

                [
                    "type" => "ckyc",
                    "message" => "Your previous CKYC request is currently being processed. Kindly wait for some time to receive the updated status.",
                    "active" => 1,
                ],

            ]
        );
    }
}
