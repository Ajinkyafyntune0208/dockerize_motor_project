<?php

namespace App\Http\Controllers\Payment;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Http\Controllers\Payment\Services\{
    goDigitPaymentGateway as CV_GODIGIT,
    reliancePaymentGateway as CV_RELIANCE,
    relianceMIscDPaymentGateway as MISD_RELIANCE,
    ackoPaymentGateway as CV_ACKO,
    hdfcErgoPaymentGateway as CV_HDFC_ERGO,
    iciciLombardPaymentGateway as PCV_ICICI_LOMBARD,
    bajaj_allianzPaymentGateway as CV_BAJAJ,
    tataAigPaymentGateway as CV_TATA_AIG,
    iffco_tokioPaymentGateway as CV_IFFCO_TOKIO,
    shriramPaymentGateway as CV_SHRIRAM,
    SbiPaymentGateway as CV_SBI,
    orientalPaymentGateway as CV_ORIENTAL,
    magmaPaymentGateway as CV_MAGMA,
    chollaMandalamPaymentGateway as CV_CHOLLA_MANDALAM,
    universalSompoPaymentGateway as CV_SOMPO,
    royalSundaramPaymentGateway as CV_ROYAL_SUNDARAM,
    unitedIndiaPaymentGateway as CV_UNITED_INDIA,
    unitedIndiaPaymentGatewayBillDesk as CV_UNITED_INDIA_BILL_DESK,
    futureGeneraliPaymentGateway as CV_FUTURE_GENERALI,
    libertyVideoconPaymentGateway as CV_LIBERTY_VIDEOCON
};
use App\Http\Controllers\Payment\Services\Pcv\V2\GoDigitPaymentGateway as CV_GODIGIT_ONEAPI;
use App\Http\Controllers\Payment\Services\Car\V2\FutureGeneraliPaymentGateway as Car_FutureGeneraliPaymentGatewayV2;
use App\Http\Controllers\Payment\Services\V1\GCV\shriramPaymentGateway AS SHRIRAM_GCV;
use App\Http\Controllers\Payment\Services\V1\GCV\FutureGeneraliPaymentGateway AS FGGCVPaymentGateway;
use App\Http\Controllers\Payment\Services\V1\PCV\shriramPaymentGateway AS SHRIRAM_PCV;
use App\Http\Controllers\Payment\Services\Bike\V1\hdfcErgoPaymentGateway as BIKE_HDFC_ERGO_V1;
use App\Http\Controllers\Payment\Services\Bike\V1\FutureGeneraliPaymentGateway as FGBIKEPaymentGateway;
use App\Http\Controllers\Payment\Services\Bike\V2\tataAigPaymentGateway;
use App\Http\Controllers\Payment\Services\V1\HdfcErgoPaymentGateway AS CV_HDFC_ERGO_V1;
use App\Http\Controllers\Payment\Services\V1\ReliancePaymentGateway AS CV_RELIANCE_V1;
use App\Http\Controllers\Payment\Services\V2\PCV\tataAigPaymentPcvGateway;
use App\Http\Controllers\Payment\Services\tataAigV2PaymentGateway;
use App\Http\Controllers\Payment\Services\V2\PCV\shriramV2PaymentGateway as CV_SHRIRAM_V2;
use App\Http\Controllers\Payment\Services\Car\{
    goDigitPaymentGateway as CAR_GODIGIT,
    chollaMandalamPaymentGateway as CAR_CHOLLA_MANDALAM,
    kotakPaymentGateway as CAR_KOTAK,
    edelweissPaymentGateway as CAR_EDELWEISS,
    futureGeneraliPaymentGateway as CAR_FUTURE_GENERALI,
    reliancePaymentGateway as CAR_RELIANCE,
    royalSundaramPaymentGateway as CAR_ROYAL_SUNDARAM,
    iciciLombardPaymentGateway as CAR_ICICI_LOMBARD,
    shriramPaymentGateway as CAR_SHRIRAM,
    iffco_tokioPaymentGateway as CAR_IFFCO_TOKIO,
    libertyVideoconPaymentGateway as CAR_LIBERTY,
    tataAigPaymentGateway as CAR_TATA_AIG,
    newIndiaPaymentGateway as CAR_NEW_INDIA,
    unitedIndiaPaymentGateway as CAR_UNITED_INDIA,
    sbiPaymentGateway as CAR_SBI,
    universalSompoPaymentGateway as CAR_SOMPO,
    hdfcErgoPaymentGateway as CAR_HDFC_ERGO,
    bajaj_allianzPaymentGateway as CAR_BAJAJ,
    magmaPaymentGateway as CAR_MAGMA,
    tataAigV2PaymentGateway as CAR_TATA_AIG_V2,
    orientalPaymentGateway as CAR_ORIENTAL,
    UnitedIndiaPaymentGatewayBillDesk as CAR_UNITED_INDIA_BILLDESK,
    UnitedIndiaPaymentGatewayRazorPay as CAR_UNITED_INDIA_RAZORPAY
};

use App\Http\Controllers\Payment\Services\Car\V2\tataAigPaymentGateway AS CAR_TATA_AIG_V2_RENEWAL; 


use App\Http\Controllers\Payment\Services\Car\V1\EdelweissPaymentGateway as EdelweissPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Car\V1\hdfcErgoPaymentGateway as CAR_HDFC_ERGO_V1;
use App\Http\Controllers\Payment\Services\Car\V1\reliancePaymentGateway as CAR_RELIANCE_V1;
use App\Http\Controllers\Payment\Services\Car\V1\shriramPaymentGateway as CAR_SHRIRAM_V1;
use App\Http\Controllers\Payment\Services\Car\V1\IffcoTokioPaymentGateway as CAR_IFFCO_TOKIO_V1;
use App\Http\Controllers\Payment\Services\Car\V1\ChollaMandalamPaymentGateway as CAR_CHOLLA_MANDALAM_V1;
use App\Http\Controllers\Payment\Services\Car\V1\BajajAllianzPaymentGateway as CAR_BAJAJ_V1;
use App\Http\Controllers\Payment\Services\Car\V1\LibertyVideoconPaymentGateway as CAR_LIBERTY_V1;
use App\Http\Controllers\Payment\Services\Car\V1\FutureGeneraliPaymentGateway as FGCARPaymentGateway;
use App\Http\Controllers\Payment\Services\Car\V2\NicPaymentGateway as CAR_NIC_V2;

use App\Http\Controllers\Payment\Services\Bike\{
    chollaMandalamPaymentGateway as BIKE_CHOLLA_MANDALAM,
    goDigitPaymentGateway as BIKE_GODIGIT,
    kotakPaymentGateway as BIKE_KOTAK,
    edelweissPaymentGateway as BIKE_EDELWEISS,
    futureGeneraliPaymentGateway as BIKE_FUTURE_GENERALI,
    reliancePaymentGateway as BIKE_RELIANCE,
    royalSundaramPaymentGateway as BIKE_ROYAL_SUNDARAM,
    iciciLombardPaymentGateway as BIKE_ICICI_LOMBARD,
    shriramPaymentGateway as BIKE_SHRIRAM,
    hdfcErgoPaymentGateway as BIKE_HDFC_ERGO,
    iffco_tokioPaymentGateway as BIKE_IFFCO_TOKIO,
    libertyVideoconPaymentGateway as BIKE_LIBERTY,
    tataAigPaymentGateway as BIKE_TATA_AIG,
    newIndiaPaymentGateway as BIKE_NEW_INDIA,
    bajaj_allianzPaymentGateway as BIKE_BAJAJ,
    unitedIndiaPaymentGateway as BIKE_UNITED_INDIA,
    UnitedIndiaPaymentGatewayBillDesk as BIKE_UNITED_INDIA_BILL_DESK,
    UnitedIndiaPaymentGatewayRazorPay as BIKE_UNITED_INDIA_RAZORPAY,
    sbiPaymentGateway as BIKE_SBI,
    universalSompoPaymentGateway as BIKE_SOMPO,
    magmaPaymentGateway as BIKE_MAGMA,
    orientalPaymentGateway as BIKE_ORIENTAL
};
use App\Http\Controllers\Payment\Services\Car\V2\GoDigitPaymentGateway as CAR_GODIGIT_ONEAPI;
use App\Http\Controllers\Payment\Services\Bike\V2\GoDigitPaymentGateway as BIKE_GODIGIT_ONEAPI; 
use App\Http\Controllers\Payment\Services\Bike\V1\ReliancePaymentGateway as BIKE_RELIANCE_V1;
use App\Http\Controllers\Payment\Services\Bike\V1\shriramPaymentGateway as shriramPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\EdelweissPaymentGateway as BIKE_EDELWEISS_V1;
use App\Http\Controllers\Payment\Services\Bike\V1\ChollaMandalamPaymentGateway as BIKE_CHOLLA_MANDALAM_V1;
use App\Http\Controllers\Payment\Services\Bike\V1\BajajAllianzPaymentGateway as BIKE_BAJAJ_ALLIANZ_V1;
use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\PolicyDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentRequestResponse;
use App\Events\PolicyGenerated;
use App\Http\Controllers\cleverTapEvent;

class ReHitPdfController extends Controller
{
    public function generatePdf(Request $request)
    {
        $validator = Validator::make($request->all(), [ 'enquiryId' => 'required' ]);
        
        if(config('enquiry_id_encryption') == 'Y' && is_numeric($request->enquiryId))
        {
            $user_product_journey_id = getDecryptedNumericEnquiryId($request->enquiryId);
            $request->merge(['enquiryId' => customEncrypt($user_product_journey_id)]);
        }
        
        if(config('enquiry_id_encryption') == 'Y')
        {
            $id = customDecrypt($request->enquiryId);
        }
        
        if ($validator->fails())
        {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        
        $date = date('Y-m-d');
        
        if (config('enquiry_id_encryption') != 'Y')
        {
            if (strlen($request->enquiryId) != 16)
            {
                return response()->json([
                    "status" => false,
                    "message" => 'Enquiry id Not Found',
                ]);
            } 
            $date = Carbon::parse( Str::substr($request->enquiryId, 0, 8) )->format('Y-m-d');
            $id = Str::substr($request->enquiryId, 8);
        }

        $user_product_journey_data = DB::table('user_product_journey')
        ->when((config('enquiry_id_encryption') == 'Y'), function ($q) {
        }, function($q1) use ($date) {
            $q1->whereDate('created_on', $date);
        })
        ->where('user_product_journey_id', $id)->first();
        if(empty($user_product_journey_data))
        {
            return response()->json([
                "status" => false,
                "message" => 'Enquiry id Not Found',
            ]);
        }
        
        $user_product_journey_id = $user_product_journey_data->user_product_journey_id;
        
        $quote_data = DB::table('quote_log as ql')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->join('user_proposal as u', 'u.user_product_journey_id', '=', 'ql.user_product_journey_id')
            ->leftJoin('policy_details as pol','pol.proposal_id', '=', 'u.user_proposal_id')
            ->leftJoin('cv_journey_stages as cv', 'cv.user_product_journey_id', '=', 'u.user_product_journey_id')
            ->leftjoin('master_product as mp', 'mp.master_policy_id', '=', 'ql.master_policy_id')
            ->where('ql.user_product_journey_id', $user_product_journey_id)
            ->select(
                'ql.user_product_journey_id', 'ql.product_sub_type_id', 'ql.ic_id', 'ql.master_policy_id',
                'mc.company_name', 'mc.company_alias',
                'mp.product_name', 'u.user_proposal_id','u.mobile_number','u.first_name','u.last_name','u.vehicale_registration_number','u.proposal_no',
                'cv.stage', 'pol.policy_number', 'pol.pdf_url'
            )
            ->first();
        $payment_success_stages = [ STAGE_NAMES['PAYMENT_SUCCESS'],STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']];
        $get_payment_details = NULL;
        if(!empty($quote_data->stage) && in_array($quote_data->stage,$payment_success_stages) && !empty($quote_data->proposal_no))
        {
            $get_payment_details = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                                  ->where('order_id', $quote_data->proposal_no)
                                  ->orderBy('id', 'desc')->first();
        }

        $brokerName = config('constants.motorConstant.SMS_FOLDER');

        if ((($quote_data->stage ?? '') == STAGE_NAMES['POLICY_ISSUED']) && !empty($quote_data->policy_number ?? '') && !empty($quote_data->pdf_url ?? ''))
        {
            if($get_payment_details != NULL)
            {
                PaymentRequestResponse::where('id', $get_payment_details->id)
                ->update(
                    [   'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'active'  => 1
                    ]
                );        
            }
            
            if (!empty($quote_data->policy_number) && !empty($quote_data->pdf_url) && $brokerName == 'ace') {
                $enquiry_id_for_ace_wa = $request->enquiryId ?? customEncrypt($quote_data->user_product_journey_id);
                $request = (object)[
                    "to"                            => $quote_data->mobile_number,
                    "type"                          => 'policyGeneratedSms',
                    "mobileNo"                      => $quote_data->mobile_number,
                    "enquiryId"                     => $enquiry_id_for_ace_wa,
                    "policyNumber"                  => $quote_data->policy_number,
                    "firstName"                     => $quote_data->first_name,
                    "vehicale_registration_number"  => $quote_data->vehicale_registration_number
                ];
                \App\Http\Controllers\Mail\MailController::ace_whatsapp($request);
            }
            cleverTapEvent::pushData($quote_data->pdf_url , $user_product_journey_id , $quote_data->mobile_number , $quote_data->product_sub_type_id);
            return response()->json([
                'status' => true,
                'msg' => 'success',
                'data' => [
                    'policy_number' => $quote_data->policy_number,
                    'pdf_link'      => filter_var($quote_data->pdf_url, FILTER_VALIDATE_URL) !== false ? $quote_data->pdf_url : file_url($quote_data->pdf_url),
                    'whatsapp_triggered' => true
                ]
            ]);
        }

        $product_type = strtolower(get_parent_code($quote_data->product_sub_type_id));
        $ic_name = $quote_data->company_alias;
        $request['product_name'] = $quote_data->product_name;
        $request['product_type'] = $product_type;
        $request['master_policy_id'] = $quote_data->master_policy_id;
        
        switch ($product_type) {
            case 'bike' :
                switch ($ic_name) {
                    case 'godigit':
                        if( app()->runningInConsole() && config("DISABLE_GODIGIT_SCHEDULER") == "Y" )
                        {
                            return false;
                            exit;
                        }
                        if ($request->is_renewal == 'Y' && config('IC.GODIGIT.V2.BIKE.RENEWAL.ENABLE') == 'Y') {
                            $return_data = BIKE_GODIGIT_ONEAPI::generatePdf($request);
                        } elseif (config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y') {
                            $return_data = BIKE_GODIGIT_ONEAPI::generatePdf($request);
                        } else {
                            $return_data = BIKE_GODIGIT::generatePdf($request);
                        }
                        break;//end godigit bike case

                    case 'cholla_mandalam':
                        if(config('IC.CHOLLA_MANDALAM.V1.BIKE.ENABLED') == 'Y'){
                            $return_data =  BIKE_CHOLLA_MANDALAM_V1::generatePdf($request);
                        }else{
                            $return_data =  BIKE_CHOLLA_MANDALAM::generatePdf($request);
                        }
                        break;//end  cholla bike case

                    case 'kotak':
                        $return_data =  BIKE_KOTAK::generatePdf($request);
                        break;//end Kotak bike case
                    case 'edelweiss':
                       if (config('IC.EDELWEISS.V1.BIKE.ENABLE') == 'Y') {
                        $return_data =  BIKE_EDELWEISS_V1::generatePdf($request);
                        } 
                        else 
                        {
                            $return_data =  BIKE_EDELWEISS::generatePdf($request);

                        }                      
                        break;

                    case 'future_generali':
                        if(config('IC.FUTURE_GENERALI.V1.BIKE.ENABLED') == 'Y')
                        {
                            $return_data  = FGBIKEPaymentGateway::generatePdf($request);
                        }
                        else
                        {
                            $return_data =  BIKE_FUTURE_GENERALI::generatePdf($request);
                        }
                        break;

                    case 'icici_lombard':
                        
                        $return_data =  BIKE_ICICI_LOMBARD::generatePdf($request);
                        break;

                    case 'reliance':
                        if(config('IC.RELIANCE.V1.BIKE.ENABLE') ==  'Y'){
                            $return_data =  BIKE_RELIANCE_V1::generatePdf($request);
                        } else {
                            $return_data =  BIKE_RELIANCE::generatePdf($request);
                        }
                        break;

                    case 'royal_sundaram':
                        $return_data =  BIKE_ROYAL_SUNDARAM::generatePdf($request);
                        break;

                    case 'liberty_videocon':
                        $return_data =  BIKE_LIBERTY::generatePdf($request);
                        break;

                    case 'tata_aig':
                        if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
                        {
                            $return_data = tataAigPaymentGateway::generatePdf($request);
                        }
                        else {
                            $return_data =  BIKE_TATA_AIG::generatePdf($request);
                        }     
                       
                        break;
                    case 'shriram':
                        if ( config('IC.SHRIRAM.V1.BIKE.ENABLE') == 'Y') {
                            $return_data =  shriramPaymentGatewayV1::generatePdf($request);
                        }
                        else{
                            $return_data =  BIKE_SHRIRAM::generatePdf($request);
                        }
                    
                        break;
                    case 'hdfc_ergo':
                        if(config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y'){
                            $return_data =  BIKE_HDFC_ERGO_V1::generatePdf($request);
                        } else {
                            $return_data =  BIKE_HDFC_ERGO::generatePdf($request);
                        }
                        break;
        
                    case 'new_india':
                        $return_data =  BIKE_NEW_INDIA::generatePdf($request);
                        break;
                    case 'iffco_tokio':
                        $return_data =  BIKE_IFFCO_TOKIO::generatePdf($request);
                        break;

                    case 'bajaj_allianz':
                        if(config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                            $return_data = BIKE_BAJAJ_ALLIANZ_V1::generatePdf($request);
                        } else {
                            $return_data = BIKE_BAJAJ::generatePdf($request);
                        }
                        break;
                    case 'united_india':
                        if(config('IC.UNITED_INDIA.BIKE.BILLDESK.ENABLE') == 'Y'){
                            $return_data = BIKE_UNITED_INDIA_BILL_DESK::generatePdf($request);                            

                        }elseif(config('IC.UNITED_INDIA.BIKE.RAZOR_PAY.ENABLE') == 'Y'){
                            $return_data = BIKE_UNITED_INDIA_RAZORPAY::generatePdf($request);
                        }else{
                            $return_data = BIKE_UNITED_INDIA::generatePdf($request);                            
                        }
                        break;
                    case 'sbi':
                        $return_data = BIKE_SBI::generatePdf($request);
                        break;
                    case 'universal_sompo':
                        $return_data = BIKE_SOMPO::generatePdf($request);
                        break;
                    case 'magma':
                        $return_data = BIKE_MAGMA::generatePdf($request);
                        break;
                    case 'oriental':
                        $return_data = BIKE_ORIENTAL::generatePdf($request);
                        break;

                    default:
                        $return_data = response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case bike
                break;//end case Bike

            case 'car' :
                switch ($ic_name) {
                    case 'godigit':
                        if( app()->runningInConsole() && config("DISABLE_GODIGIT_SCHEDULER") == "Y" )
                        {
                            return false;
                            exit;
                        }
                        if ($request->is_renewal == 'Y' && config('IC.GODIGIT.V2.CAR.RENEWAL.ENABLE') == 'Y') {
                            $return_data = CAR_GODIGIT_ONEAPI::generatePdf($request);
                        }
                        else if (config('IC.GODIGIT.V2.CAR.ENABLE') == 'Y') {
                            $return_data = CAR_GODIGIT_ONEAPI::generatePdf($request);
                        }
                        else
                        {
                            $return_data = CAR_GODIGIT::generatePdf($request);
                        }
                        break;//end godigit car case

                    case 'cholla_mandalam':
                        if(config('IC.CHOLLA_MANDALAM.V1.CAR.ENABLED') == 'Y'){
                            $return_data = CAR_CHOLLA_MANDALAM_V1::generatePdf($request);
                        }else{
                            $return_data = CAR_CHOLLA_MANDALAM::generatePdf($request);
                        } 
                        break;//end cholla car case

                    case 'kotak':
                        $return_data = CAR_KOTAK::generatePdf($request);
                        break;//end Kotak car case

                    case 'edelweiss':
                        if (config('IC.EDELWEISS.V1.CAR.ENABLE') == 'Y') {
     
                            $return_data =  EdelweissPaymentGatewayV1::generatePdf($request);
                        } 
                        else 
                        {
                            $return_data = CAR_EDELWEISS::generatePdf($request);
                        }
                      
                        break;

                    case 'future_generali':
                        if(config('IC.FUTURE_GENERLI.V1.CAR.ENABLED') == 'Y')
                        {
                            $return_data = FGCARPaymentGateway::generatePdf($request);
                        }
                        elseif(config("IC.FUTURE_GENERALI.V2.CAR.ENABLED") == 'Y')
                        {
                            $return_data = Car_FutureGeneraliPaymentGatewayV2::generatePdf($request);
                        }
                        else
                        {
                            $return_data = CAR_FUTURE_GENERALI::generatePdf($request);
                        }
                        break;

                    case 'icici_lombard':
                        $return_data = CAR_ICICI_LOMBARD::generatePdf($request);
                        break;

                    case 'reliance':
                        if(config('IC.RELIANCE.V1.CAR.ENABLE') == 'Y'){
                            $return_data = CAR_RELIANCE_V1::generatePdf($request);
                        } else {
                            $return_data = CAR_RELIANCE::generatePdf($request);
                        }
                        break;
                    case 'royal_sundaram':
                        $return_data = CAR_ROYAL_SUNDARAM::generatePdf($request);

                        break;

                    case 'liberty_videocon':
                        if (config('IC.LIBERTY_VIDEOCON.V1.CAR.ENABLE') == 'Y'){
                            $return_data = CAR_LIBERTY_V1::generatePdf($request);
                        } else {
                            $return_data = CAR_LIBERTY::generatePdf($request);
                        }
                        break;

                    case 'tata_aig':
                        if ($request->is_renewal == 'Y' && config('IC.TATA.V2.CAR.RENEWAL.ENABLE') == 'Y') {
                            $return_data = CAR_TATA_AIG_V2_RENEWAL::generatePdf($request);
                        }
                        else
                        {
                            $return_data = CAR_TATA_AIG::generatePdf($request);
                        }
                        
                        break;
                    case 'shriram':
                        if(config('IC.SHRIRAM.V1.CAR.ENABLE') == 'Y')
                        {
                        $return_data = CAR_SHRIRAM_V1::generatePdf($request);
                        }
                        else
                        {
                            $return_data = CAR_SHRIRAM::generatePdf($request);
                        }
                        break;
                    case 'new_india':
                        $return_data = CAR_NEW_INDIA::generatePdf($request);
                        break;
                    case 'united_india':
                        if(config('IC.UNITED_INDIA.CAR.BILLDESK.ENABLE') == 'Y'){
                            $return_data = CAR_UNITED_INDIA_BILLDESK::generatePdf($request);                            

                        }elseif(config('IC.UNITED_INDIA.CAR.RAZOR_PAY.ENABLE') == 'Y'){
                            $return_data = CAR_UNITED_INDIA_RAZORPAY::generatePdf($request);
                        }else{
                            $return_data = CAR_UNITED_INDIA::generatePdf($request);                            
                        }
                        break;
                    case 'universal_sompo':
                        $return_data = CAR_SOMPO::generatePdf($request);
                        break;
                    case 'hdfc_ergo':
                        if(config('IC.HDFC_ERGO.V1.CAR.ENABLE') == 'Y'){
                            $return_data = CAR_HDFC_ERGO_V1::generatePdf($request);
                        } else {
                            $return_data = CAR_HDFC_ERGO::generatePdf($request);
                        }

                        break;
                    case 'sbi':
                        $return_data = CAR_SBI::generatePdf($request);
                        break;
                    case 'bajaj_allianz':
                        if(config('IC.BAJAJ_ALLIANZ.V1.CAR.ENABLE') == 'Y'){
                            $return_data = CAR_BAJAJ_V1::generatePdf($request);
                        } else {
                            $return_data = CAR_BAJAJ::generatePdf($request);
                        }
                        break;
                    case 'iffco_tokio':
                        if (config('IC.IFFCO_TOKIO.V1.CAR.ENABLE') == 'Y') {
     
                            $return_data =CAR_IFFCO_TOKIO_V1::generatePdf($request);
                        } 
                        else{
                            $return_data = CAR_IFFCO_TOKIO::generatePdf($request);
                        }
                       
                        break;
                    case 'tata_aig_v2':
                        $return_data = CAR_TATA_AIG_V2::generatePdf($request);
                        break;
                    case 'oriental':
                        $return_data = CAR_ORIENTAL::generatePdf($request);
                        break;
                    case 'magma':
                        $return_data = CAR_MAGMA::generatePdf($request);
                        break;
                    case 'nic':
                        $return_data = CAR_NIC_V2::generatePdf($request);
                        break;

                    default:
                        $return_data = response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case car
                break;//end case car

            case 'pcv' :
            case 'gcv' :
                switch ($ic_name) {
                    case 'godigit':
                        if( app()->runningInConsole() && config("DISABLE_GODIGIT_SCHEDULER") == "Y" )
                        {
                            return false;
                            exit;
                        }
                        if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')) {
                            $return_data = CV_GODIGIT_ONEAPI::generatePdf($request);
                        } else {
                            $return_data = CV_GODIGIT::generatePdf($request);
                        }
                        //$return_data = CV_GODIGIT::generatePdf($request);
                        break;//end godigit pcv gcv case

                    case 'icici_lombard':
                        $return_data = PCV_ICICI_LOMBARD::generatePdf($request);
                        break;//end icici lombard pcv gcv case

                    case 'reliance':
                        if (config('IC.RELIANCE.V1.CV.ENABLE') == 'Y') {
                            $return_data = CV_RELIANCE_V1::generatePdf($request);
                        } else {
                            $return_data = CV_RELIANCE::generatePdf($request);
                        }
                        break;//end reliance pcv gcv case

                    case 'acko':
                        $return_data = CV_ACKO::generatePdf($request);
                        break;//end acko pcv gcv case

                    case 'hdfc_ergo':
                        if (config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y') {
                            $return_data = CV_HDFC_ERGO_V1::generatePdf($request);
                        } else {
                            $return_data = CV_HDFC_ERGO::generatePdf($request);
                        }
                        break;
                    case 'bajaj_allianz':
                        $return_data = CV_BAJAJ::generatePdf($request);
                        break;
                    case 'tata_aig':
                        if(config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y')
                        {
                            $return_data =  tataAigPaymentPcvGateway::generatePdf($request);
                        }
                        elseif((config('IC.TATA_AIG.V2.GCV.ENABLE') == 'Y')) {
                            $return_data = tataAigV2PaymentGateway::generatePdf($request);
                        }     
                        $return_data = CV_TATA_AIG::generatePdf($request);
                        break;
                    case 'iffco_tokio':
                        $return_data = CV_IFFCO_TOKIO::generatePdf($request);
                        break;
                    case 'shriram':
                        if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                           $return_data = CV_SHRIRAM_V2::generatePdf($request);
                        }
                        elseif(config('IC.SHRIRAM.V1.GCV.ENABLE') == 'Y' && in_array(policyProductType($request->policyId)->parent_id , [4]))
                        {
                            $return_data =  SHRIRAM_GCV::generatePdf($request);
                        }
                        elseif((config('IC.SHRIRAM.V1.PCV.ENABLE') == 'Y') && in_array(policyProductType($request->policyId)->parent_id , [8])) {
                           //
                            $return_data = SHRIRAM_PCV::generatePdf($request);
                        }   
                        $return_data = CV_SHRIRAM::generatePdf($request);
                        break;
                    case 'sbi': 
                        $return_data = CV_SBI::generatePdf($request);
                        break;
                    case 'oriental':
                        $return_data = CV_ORIENTAL::generatePdf($request);
                        break;
                    case 'magma':
                        $return_data = CV_MAGMA::generatePdf($request);
                        break;
                    case 'cholla_mandalam':
                        $return_data = CV_CHOLLA_MANDALAM::generatePdf($request);
                        break;
                    case 'united_india':
                        if(config('IC.UNITED_INDIA.CV.BILLDESK.ENABLE') == 'Y'){
                            $return_data = CV_UNITED_INDIA_BILL_DESK::generatePdf($request);                            

                        }else{
                            $return_data = CV_UNITED_INDIA::generatePdf($request);                            
                        }
                        break;
                    case 'universal_sompo':
                        $return_data = CV_SOMPO::generatePdf($request);
                        break;
                    case 'royal_sundaram':
                        $return_data = CV_ROYAL_SUNDARAM::generatePdf($request);
                        break;
                    case 'future_generali':
                        if(config('IC.FUTURE_GENERALI.V1.GCV.ENABLED') == 'Y')
                        {
                            $return_data = FGGCVPaymentGateway::generatePdf($request);
                        }
                        else
                        {
                            $return_data = CV_FUTURE_GENERALI::generatePdf($request);
                        }
                        break;
                    case 'liberty_videocon':
                        $return_data =  CV_LIBERTY_VIDEOCON::generatePdf($request);
                        break;

                    default:
                        $return_data = response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case pcv gcv
                break;//end case pcv gcv

            case 'misc' :
                switch ($ic_name) {
                    case 'reliance':
                        $return_data = MISD_RELIANCE::generatePdf($request);
                        break;//end reliance pcv gcv case
                 
                    case 'icici_lombard':
                        $return_data = PCV_ICICI_LOMBARD::generatePdf($request);
                        break;//end icici misc case

                    case 'universal_sompo':
                        $return_data = CV_SOMPO::generatePdf($request);
                        break;

                    case 'sbi':
                        $return_data = CV_SBI::generatePdf($request);
                        break;    

                    default:
                        $return_data = response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                        break;
                }
                break;

            default:
                $return_data = response()->json([
                    'status' => false,
                    'msg' => 'Invalid Product Type'
                ]);
        }
        if(config('constants.motorConstant.GRAMCOVER_DATA_PUSH_ENABLED') == 'Y'){
            // \App\Jobs\GramcoverDataPush::dispatch(customDecrypt($request->enquiryId));
        }
        if(!empty($get_payment_details->id) && is_array($return_data) && $return_data['status'])
        {
            cleverTapEvent::pushData($return_data['data']['pdf_url'] , $user_product_journey_id , $quote_data->mobile_number ,$quote_data->product_sub_type_id);
            PaymentRequestResponse::where('id', $get_payment_details->id)
                    ->update(['status' => STAGE_NAMES['PAYMENT_SUCCESS'],'active'  => 1]);            
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('policy_details', 'rehit_source')) {
          $type =  app()->runningInConsole() ? 'CRON' : 'MANUAL';
          $check = checkRehitSourceStatusExist($quote_data->user_proposal_id, $type);
          if(!$check)
          {
            if ($return_data instanceof \Illuminate\Http\JsonResponse) {
                $d = $return_data->original;
                if (isset($d['status']) && $d['status']) {
                    PolicyDetails::where('proposal_id', $quote_data->user_proposal_id)
                        ->update(['rehit_source' => $type]);
                }
            } else if (isset($data['status']) && $data['status']) {
                PolicyDetails::where('proposal_id', $quote_data->user_proposal_id)
                    ->update(['rehit_source' => $type ]);
            }
          }
        }

        if(isset($return_data->original) && is_array($return_data->original))
        {
            $data = $return_data->original;
            if( in_array( $data['msg'], [ STAGE_NAMES['POLICY_PDF_GENERATED']] ) && !empty($user_product_journey_id))
            {
                $data_update['user_product_journey_id'] = $user_product_journey_id;
                $data_update['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data_update);
                // MONGO PUSH
                PolicyGenerated::class;
            }
        }

        return $return_data;
    }

    public function generatePdfAll(Request $request)
    {
        $reports = \App\Models\UserProductJourney::with([
            'journey_stage',
        ]);

        if ($request->processing_type == 'failure_cases') {
            $reports = $reports->whereHas('journey_stage', function ($query) use ($request) {
                if ($request->process_range == 1) {
                    // It will run on everyday, processing only last one whole day records.
                    $date_range = [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()];
                } elseif ($request->process_range == 7) {
                    // It will run on every monday, processing data from sunday to saturday records.
                    $date_range = [now()->subDays(7)->startOfDay(), now()->subDays(2)->endOfDay()];
                }
                # $query->where('created_at', '>', now()->subDays(7)->startOfDay());
                # $query->whereBetween('updated_at', $date_range);
                $query->whereBetween('created_at', $date_range);
                $query->whereIn('stage', [ STAGE_NAMES['PAYMENT_FAILED']]);
            });
        } else {
            $reports = $reports->whereHas('journey_stage', function ($query) use ($request) {
                ## 2 Days Logic
                if ($request->date_range == '2 days') {
                    $date_range = [now()->subDays(4)->startOfDay(), now()->subDay()->endOfDay()];
                }
                ## Today Logic
                elseif ($request->date_range == 'todays') {
                    $date_range = [now()->startOfDay(), now()->endOfDay()];
                }
                ## Default Logic
                else {
                    $date_range = [now()->startOfDay(), now()];
                }
                
                # $query->where('created_at', '>', now()->subDays(7)->startOfDay());

                if (empty($request->all())) {
                    # $query->whereBetween('updated_at', $date_range);
                    $query->whereBetween('created_at', $date_range);
                }

                if (empty($request->all()) || !empty($request->date_range)) {
                    $query->whereIn('stage', [ STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_INITIATED']]);
                }

            });
        }

        if( in_array( $request->time, [ '5 minutes', '15 minutes', '30 minutes', '120 minutes' ] ) )
        {
            $reports = $reports->whereHas('journey_stage', function ($query) use ($request)
            {
                if($request->time == '5 minutes')
                {
                    $query->whereBetween('updated_at', [now()->startOfDay(), now()->endOfDay()]);
                    $query->whereIn('stage', [ STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']]);
                }
                elseif($request->time == '15 minutes')
                {
                    $query->whereBetween('updated_at', [now()->subMinutes(15)->startOfMinute(), now()->subMinutes(15)->endOfMinute()]);
                    $query->where('stage', STAGE_NAMES['PAYMENT_INITIATED']);
                }
                else if($request->time == '30 minutes')
                {
                    $query->whereBetween('updated_at', [now()->subMinutes(30)->startOfMinute(), now()->subMinutes(30)->endOfMinute()]);
                    $query->where('stage', STAGE_NAMES['PAYMENT_INITIATED']);
                }
                else if($request->time == '120 minutes')
                {
                    $query->whereBetween('updated_at', [now()->subHours(2)->startOfMinute(), now()->subHours(2)->endOfMinute()]);
                    $query->where('stage', STAGE_NAMES['PAYMENT_INITIATED']);
                }
                $query->where( 'created_at', '>', now()->subDays(7)->startOfDay() );
            });
        }
        
        $reports = $reports->get();
        $result = [];
        
        foreach ($reports as $key => $report) 
        {
            if( in_array( $request->time, [ '15 minutes', '30 minutes', '120 minutes' ] ) )
            {
                if($report->journey_stage->stage == STAGE_NAMES['PAYMENT_INITIATED'])
                {
                    $restricted_id = \App\Models\RehitRestrictEnq::where([ 'user_product_journey_id' => $report->user_product_journey_id ])->first();
                    if( isset( $restricted_id->attempts ) && $restricted_id->attempts > 2)
                    {
                        continue;
                    }
                }
            }
            $data = [ 'enquiryId' => $report->journey_id ];
            try
            {
                $result[$key] = $this->generatePdf(new Request($data));
            }
            catch (\Throwable $th)
            {
                Log::info('Cron Rehit failed for Trace ID : '.$report->journey_id.' ' . $th);
            }
            
            if( in_array( $request->time, [ '15 minutes', '30 minutes', '120 minutes' ] ) )
            {
                if($report->journey_stage->stage == STAGE_NAMES['PAYMENT_INITIATED'])
                {
                    if( isset( $restricted_id->attempts ) )
                    {
                        $restricted_id->attempts = $restricted_id->attempts + 1;
                        $restricted_id = \App\Models\RehitRestrictEnq::where([ 'user_product_journey_id' => $report->user_product_journey_id])->update(['attempts' => $restricted_id->attempts + 1]);
                    }
                    else
                    {
                        $restricted_id = \App\Models\RehitRestrictEnq::create([ 'user_product_journey_id' => $report->user_product_journey_id, 'attempts' => 1 ]);
                    }
                }
            }
        }
        return $result;
    }
}