<?php

namespace App\Http\Controllers;

use App\Jobs\DashboardDataPush;
use App\Jobs\KafkaCvDataPushJob;
use App\Jobs\KafkaDataPushJob;
use App\Models\UserJourneyKafkaPaymentStatus;
use App\Models\UserProductJourney;
use App\Notifications\KafkaDataPushValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class KafkaController extends Controller
{
    public static function ManualDataPush(Request $request, $enquiry_id, $isIdEncrypted = true)
    {
        try {
            $enquiry_id = $isIdEncrypted ? customDecrypt($request->enquiryId) : $enquiry_id;
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => 'Error while decrypting the enquiry id',
                'dev' => $e->getMessage(),
            ]);
        }
        $user_journey_id = UserProductJourney::find($enquiry_id);
        if (config('constants.motorConstant.KAFKA_DATA_PUSH_ENABLED') == 'Y') {
            if (in_array($user_journey_id->product_sub_type_id, [1, 2])) { // If Enquiry ID is not of car and bike then it is Commercial Vehicle
                KafkaDataPushJob::dispatch($enquiry_id, 'policy', 'manual')->onQueue(env('QUEUE_NAME'));
            } else {
                KafkaCvDataPushJob::dispatch($enquiry_id, 'policy', 'manual')->onQueue(env('QUEUE_NAME'));
            }
        } else if (config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') == 'Y') {
            DashboardDataPush::dispatch($enquiry_id)->onQueue(env('DASHBOARD_PUSH_QUEUE_NAME'));
        }
        return response()->json([
            'status' => true,
            'msg' => 'Manual Data push initiated.',
        ]);
    }

    public function ValidateKafkaPayload(Int $enquiry_id, array $payload = [])
    {
        pushTraceIdInLogs($enquiry_id);
        try {
            $payment_validation_error = '';
            if (
                config('IS_KAFKA_PAYMENT_STATUS_VALIDATION_ENABLED') == 'Y' &&
                (is_array($payload['payment']) && !empty($payload['payment']))
            ) {
                $payment_validation_error = $this->paymentStatusValidation($enquiry_id, $payload['payment']);
            }
            if (config('IS_KAFKA_VALIDATION_ENABLED') != 'Y') {
                return false;
            }
            $validator = new \Illuminate\Support\Facades\Validator;
            $validation_matrix = ['customer', 'proposal', 'payment', 'policy', 'inspection'];
            $failed_validations = [];
            foreach ($validation_matrix as $v) {
                $functionName = 'get' . ucwords($v) . 'Rules';
                if (isset($payload[$v]) && !empty($payload[$v]) && is_array($payload[$v]) && method_exists(__CLASS__, $functionName)) {
                    $getValidationRules = $this->{$functionName}();
                    if (!empty($getValidationRules)) {
                        $valMake = $validator::make($payload[$v], $this->{$functionName}());
                        if ($valMake->fails()) {
                            $failed_validations[$v] = $valMake->errors();
                        }
                    }
                }
            }
            $send_mail = false;
            $message = '';
            if (!empty($failed_validations)) {
                $message = 'Kafka Validation Error (' . ($payload['enquiry_id'] ?? '') . ') : ' . json_encode($failed_validations);
                $send_mail = true;
            }
            if (!empty($payment_validation_error)) {
                $message .= PHP_EOL . $payment_validation_error;
                $send_mail = true;
            }

            if ($send_mail) {
                $detail = [
                    'title' => 'Important !! Kafa Data Push issue - ' . date('d-m-Y'),
                    'name' => 'Team',
                    'trace_id' => customEncrypt($enquiry_id),
                    'error_msg' => $message,
                ];
                if (config('ENABLE_KAFKA_DATA_PUSH_SEND_MAIL') == 'Y') {
                    $mailObject = new \App\Mail\KafkaJobFailedMail($detail);
                    \Illuminate\Support\Facades\Mail::to(explode(',', config('KAFKA_DATA_PUSH_SEND_MAIL_TO')))->send($mailObject);
                } else {
                    \Illuminate\Support\Facades\Log::warning($detail['title'] . ' : ' . $message);
                }
                if (!empty($kafkaSlackUrl = getCommonConfig('slack.kafkaFailedValidation.channel.url'))) {
                    Notification::route('slack', $kafkaSlackUrl)->notify(new KafkaDataPushValidation($detail));
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning($e);
        }
    }

    public function getProposalRules()
    {
        return [
            'insurer_id' => 'required|Integer|Numeric',
            'rto_id' => 'required|Integer|Numeric',
            'variant_id' => 'required|Integer|Numeric',
        ];
    }

    public function getCustomerRules()
    {
        return [

        ];
    }

    public function getPaymentRules()
    {
        return [

        ];
    }

    public function getInspectionRules()
    {
        return [

        ];
    }

    public function getPolicyRules()
    {
        return [

        ];
    }

    public function paymentStatusValidation($enquiry_id, array $payment_array = []): string
    {
        if (empty($payment_array)) {
            return '';
        }

        $getOriginalStatus = UserJourneyKafkaPaymentStatus::where('user_product_journey_id', $enquiry_id)->pluck('stage')->first();
        if (empty($getOriginalStatus)) {
            UserJourneyKafkaPaymentStatus::create(['user_product_journey_id' => $enquiry_id, 'stage' => $payment_array['payment_status']]);
            return '';
        }

        $payment_matrix = [
            "success" => 4,
            "payment_deducted" => 3,
            "pending" => 2,
            "failure" => 1,
            "inspection_raised" => 0,
        ];
        $existing_priority = $payment_matrix[$getOriginalStatus] ?? false;
        if ($existing_priority === false) {
            return '';
        }
        $upcoming_priority = $payment_matrix[$payment_array['payment_status']] ?? false;
        if ($upcoming_priority === false) {
            return '';
        }
        if ($upcoming_priority < $existing_priority) {
            return 'Upcoming status - (' . $payment_array["payment_status"] . ') ' . $upcoming_priority . ', Existing status - (' . $getOriginalStatus . ') ' . $existing_priority;
        } else if ($upcoming_priority != $existing_priority) {
            UserJourneyKafkaPaymentStatus::where('user_product_journey_id', $enquiry_id)->update(['stage' => $payment_array['payment_status']]);
        }
        return '';
    }

    public static function considerDataFromPremiumDetails(Int $product_sub_type_id, String $company_alias = ''): bool
    {
        if (empty($company_alias) || empty($product_sub_type_id)) {
            return false;
        }
        $product = match ($product_sub_type_id) {
            1 => 'car',
            2 => 'bike',
            default => 'cv'
        };

        // Values will be for eg. bajaj_allianz:car,bike~shriram:bike~royal_sundaram:bike,cv~sbi:car,bike,cv
        $ics_and_product = collect(explode('~', config('ALLOWED_ICS_AND_PRODUCT_FOR_PREMIUM_DETAILS_KAFKA_SYNC'))); // bajaj_allianz:car,bike
        if (empty($ics_and_product->first())) {
            return false;
        }
        $list = [];
        $ics_and_product->each(function ($each_ic) use (&$list) {
            list($ic_name, $products) = explode(':', $each_ic);
            $array = [
                'ic_name' => $ic_name,
                'products' => explode(',', $products),
            ];
            $list[] = $array;
        });

        $lists = collect($list);
        $existing_ic = $lists->where('ic_name', '=', $company_alias)->first();
        if (empty($existing_ic)) {
            return false;
        }
        return in_array($product, $existing_ic['products']);
    }
}
