<?php

namespace App\Jobs;

use App\Imports\UspImport;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RenewbuyRc implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $excel_data = Excel::toArray(new UspImport(), storage_path('app/public/Renewals Data Set.xls'));
        $car_file = Storage::path(\Illuminate\Support\Str::uuid()->toString() . '_Car.csv');
        $bike_file = Storage::path(\Illuminate\Support\Str::uuid()->toString() . '_Bike.csv');
        $car_file = fopen($car_file, "w");
        $bike_file = fopen($bike_file, "w");

        foreach ($excel_data as $sheet_no => $sheet) {

            if($sheet_no == 0)
                continue;

            foreach ($sheet as $key => $value) {
                if ($key == 0) {
                    $headers = array_keys($sheet[0]);
                    array_push($headers, 'enquiry_id');
                    array_push($headers, 'quote_url');

                    if ($value['vehicle_type2w4w'] == 'Two Wheeler') {
                        fputcsv($bike_file, $headers);
                    } else if ($value['vehicle_type2w4w'] == 'Four Wheeler') {
                        fputcsv($car_file, $value);
                    }
                }
                try {
                    $rc_number = str_split($value['registration_no']);
                    if ($rc_number[0] . $rc_number[1] == 'DL') {
                        $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                        $rto_code = $new_rc_number;
                        $str = substr($value['registration_no'], 3);
                        $str2 = substr($str, 0, -4);
                        $str3 = substr($str, -4);
                        $new_rc_number .= '-' . $str2 . '-' . $str3;
                    } else {
                        $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                        $rto_code = $new_rc_number;
                        $str = substr($value['registration_no'], 4);
                        $str2 = substr($str, 0, -4);
                        $str3 = substr($str, -4);
                        $new_rc_number .= '-' . $str2 . '-' . $str3;
                    }

                    $enquiry_id = httpRequestNormal(config('rc_url') . '/api/createEnquiryId', 'POST', [
                        "firstName" => null,
                        "lastName" => null,
                        "emailId" => null,
                        "mobileNo" => null,
                        "isSkipped" => true
                    ])['response']['data']['enquiryId'];

                    $request = [
                        "stage" => "1",
                        "userProductJourneyId" => $enquiry_id,
                        "enquiryId" => $enquiry_id,
                        "whatsappConsent" => true,
                    ];
                    httpRequestNormal(config('rc_url') . '/api/saveQuoteRequestData', 'POST', $request);

                    if ($value['vehicle_type2w4w'] == 'Two Wheeler') {
                        $section = 'bike';
                        $productSubType = "1";
                    } else if ($value['vehicle_type2w4w'] == 'Four Wheeler') {
                        $section = 'car';
                        $productSubType = "1";
                    }
                    $request = [
                        'enquiryId' => $enquiry_id,
                        'registration_no' => $new_rc_number ?? null,
                        "productSubType" => $productSubType,
                        "section" => $section,
                        "is_renewal" => "Y",
                    ];
                    $vehicleDetails = httpRequestNormal(config('rc_url') . '/api/getVehicleDetails', 'GET', $request, [], ['accept' => 'application/json']);
                    if ($vehicleDetails['status'] == 200) {
                        $vehicleDetails = $vehicleDetails['response'];
                        if ($vehicleDetails['status'] == true && !empty($vehicleDetails['data'])) {
                            $frontend_url = httpRequestNormal(config('rc_url') . "/api/frontendUrl", "GET")['response']['data'];
                            if (isset($vehicleDetails['data']['ft_product_code'])) {
                                if ($vehicleDetails['data']['ft_product_code'] == 'car') {
                                    $url = $frontend_url['car_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                } else if ($vehicleDetails['data']['ft_product_code'] == 'bike') {
                                    $url = $frontend_url['bike_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                } else {
                                    $url = $frontend_url['cv_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                }
                            } else if (isset($vehicleDetails['data']['additional_details']['productSubTypeId'])) {
                                $productSubType = $vehicleDetails['data']['additional_details']['productSubTypeId'];
                                if (get_parent_code($vehicleDetails['data']['additional_details']['productSubTypeId']) == 'CAR') {
                                    $url = $frontend_url['car_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                } else if (get_parent_code($vehicleDetails['data']['additional_details']['productSubTypeId']) == 'BIKE') {
                                    $url = $frontend_url['bike_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                } else {
                                    $url = $frontend_url['cv_frontend_url'] . '/quotes?enquiry_id=' . $enquiry_id;
                                }
                            }
                            httpRequestNormal(config('rc_url') . '/api/updateJourneyUrl', 'POST', [
                                'user_product_journey_id' => $enquiry_id,
                                'quote_url' => $url,
                                'stage' => STAGE_NAMES['QUOTE']
                            ]);

                            $request = [
                                "isRenewalRedirection" => "N",
                                "enquiryId" => $enquiry_id,
                                "vehicleRegistrationNo" => $new_rc_number ?? null,
                                "userProductJourneyId" => $enquiry_id,
                                "corpId" => "",
                                "userId" => null,
                                "productSubTypeId" =>  $productSubType,
                                "fullName" =>  null,
                                "firstName" => null,
                                "lastName" => null,
                                "emailId" => null,
                                "mobileNo" => null,
                                "policyType" => $vehicleDetails['data']['additional_details']['policyType'] ?? null,
                                "businessType" => $vehicleDetails['data']['additional_details']['businessType'] ?? null,
                                "rto" => $vehicleDetails['data']['additional_details']['rto']  ?? null,
                                "manufactureYear" => $vehicleDetails['data']['additional_details']['manufactureYear']  ?? null,
                                "version" => $vehicleDetails['data']['additional_details']['version']  ?? null,
                                "versionName" => $vehicleDetails['data']['additional_details']['versionName']  ?? null,
                                "vehicleRegisterAt" => $vehicleDetails['data']['additional_details']['vehicleRegisterAt']  ?? null,
                                "vehicleRegisterDate" => $vehicleDetails['data']['additional_details']['vehicleRegisterDate']  ?? null,
                                "vehicleOwnerType" => $vehicleDetails['data']['additional_details']['vehicleOwnerType']  ?? null,
                                "hasExpired" => $vehicleDetails['data']['additional_details']['hasExpired']  ?? null,
                                "isNcb" => $vehicleDetails['data']['additional_details']['isNcb']  ?? null,
                                "isClaim" => $vehicleDetails['data']['additional_details']['isClaim']  ?? null,
                                "fuelType" => $vehicleDetails['data']['additional_details']['fuelType']  ?? null,
                                "vehicleUsage" => $vehicleDetails['data']['additional_details']["vehicleUsage"]  ?? null,
                                "vehicleLpgCngKitValue" => "",
                                "previousInsurer" => "",
                                "previousInsurerCode" => "",
                                "previousPolicyType" => $vehicleDetails['data']['additional_details']["previousPolicyType"]  ?? null,
                                "modelName" => $vehicleDetails['data']['additional_details']['modelName']  ?? null,
                                "manfactureName" => $vehicleDetails['data']['additional_details']['manfactureName']  ?? null,
                                "ownershipChanged" => $vehicleDetails['data']['additional_details']['ownershipChanged']  ?? null,
                                "leadJourneyEnd" => true,
                                "stage" => 11,
                                "applicableNcb" => $vehicleDetails['data']['additional_details']['applicableNcb']  ?? null,
                                "manfactureId" => $vehicleDetails['data']['additional_details']['manfactureId']  ?? null,
                                "model" => $vehicleDetails['data']['additional_details']['model']  ?? null,
                                "policyExpiryDate" => $vehicleDetails['data']['additional_details']['policyExpiryDate']  ?? null,
                                "previousNcb" => $vehicleDetails['data']['additional_details']['previousNcb']  ?? null,
                            ];
                            httpRequestNormal(config('rc_url') . '/api/saveQuoteRequestData', 'POST', $request);
                            if (isset($vehicleDetails['data']['results'][0]['vehicle']['eng_no']) && isset($vehicleDetails['data']['results'][0]['vehicle']['chasi_no'])) {
                                $request = array_merge($request, [
                                    "engineNo" => $vehicleDetails['data']['results'][0]['vehicle']['eng_no'] ?? null,
                                    "chassisNo" => $vehicleDetails['data']['results'][0]['vehicle']['chasi_no'] ?? null,
                                    "vehicleColor" => $vehicleDetails['data']['results'][0]['vehicle']['color'] ?? null
                                ]);
                            }

                            if (isset($vehicleDetails['data']['additional_details']["previous_insurer"])) {
                                $request = array_merge($request, ["previous_insurer" => $vehicleDetails['data']['additional_details']["previous_insurer"] ?? null]);
                            }
                            if (isset($vehicleDetails['data']['additional_details']["previous_insurer_code"])) {
                                $request = array_merge($request, ["previous_insurer_code" => $vehicleDetails['data']['additional_details']["previous_insurer_code"] ?? null]);
                            }
                            httpRequestNormal(config('rc_url') . '/api/saveQuoteRequestData', 'POST', $request);
                            $request = ["enquiryId" => $enquiry_id, "leadStageId" => 2];
                            httpRequestNormal(config('rc_url') . '/api/updateUserJourney', 'POST', $request);

                            $value['enquiry_id'] = "'" . $enquiry_id;
                            $value['url'] = config('rc_url') . '?' . $url;
                        }
                    } else {
                        $value['enquiry_id'] = "'" . $enquiry_id;
                        $value['url'] = json_encode($vehicleDetails['response'] ??[]);
                    }
                } catch (\Exception $e) {
                    $value['url'] = ' Line No. ' . $e->getLine() . ' Message ' . $e->getMessage();
                }
                if ($value['vehicle_type2w4w'] == 'Two Wheeler') {
                    fputcsv($bike_file, $value);
                } else if ($value['vehicle_type2w4w'] == 'Four Wheeler') {
                    fputcsv($car_file, $value);
                }
            }
        }

        fclose($car_file);
        fclose($bike_file);
    }
}
