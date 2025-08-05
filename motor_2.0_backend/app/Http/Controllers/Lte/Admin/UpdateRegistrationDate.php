<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\RegistrationDateUpdateLog;

class UpdateRegistrationDate extends Controller
{

    public function index(Request $request)
    {

        $registration_date = null;
        $manf_date = null;
        $invoice_date = null;

        if ($request->has('enquiry_id')) {
            $enquiryId = acceptBothEncryptDecryptTraceId($request->enquiry_id);

            $quoteRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();

            if ($quoteRequest) {
                $registration_date = $quoteRequest->vehicle_register_date ? Carbon::parse($quoteRequest->vehicle_register_date)->format('Y-m-d') : null;
                $manf_date = $quoteRequest->manufacture_year ? Carbon::createFromFormat('m-Y', $quoteRequest->manufacture_year)->format('Y-m')  : null;
                $invoice_date = $quoteRequest->vehicle_invoice_date ? Carbon::parse($quoteRequest->vehicle_invoice_date)->format('Y-m-d') : null;
            }
        }
        return view('admin_lte.updateregistrationdate.index', compact('registration_date', 'manf_date','invoice_date'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('update_registration_date.edit')) {
            response('unauthorized action', 401);
        }

        $validator = Validator::make($request->all(), [
            'enquiry_id' => 'required',
            'manufacture_date' => 'required',
            'registration_date' => 'required',
            'invoice_date' => 'required'

        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Date cannot be empty']);
        }
        $enquiryId = acceptBothEncryptDecryptTraceId($request->enquiry_id);

        $journey_stage = JourneyStage::where('user_product_journey_id', $enquiryId)->value('stage');

        if (empty($journey_stage)) {
            return response()->json(['success' => false, 'message' => 'Invalid trace-Id']);
        }

        $allow_stage = [
            STAGE_NAMES['LEAD_GENERATION'],
            STAGE_NAMES['PROPOSAL_DRAFTED'],
            STAGE_NAMES['QUOTE'],
        ];

        if (in_array($journey_stage, $allow_stage)) {

            $manufactureYear = date('m-Y', strtotime($request->manufacture_date));
            $registrationDate = date('d-m-Y', strtotime($request->registration_date));
            $invoiceDate = date('d-m-Y',strtotime($request->invoice_date));

            $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

            if ($corporate_vehicles_quotes_request) {


                if (
                    $corporate_vehicles_quotes_request->manufacture_year !== $manufactureYear ||
                    $corporate_vehicles_quotes_request->vehicle_register_date !== $registrationDate ||
                    $corporate_vehicles_quotes_request->vehicle_invoice_date !== $invoiceDate
                ) {

                    RegistrationDateUpdateLog::create([
                        'enquiry_id' => $enquiryId,
                        'old_date' => ['manf_dat' => $corporate_vehicles_quotes_request->manufacture_year, 'reg_dat' => $corporate_vehicles_quotes_request->vehicle_register_date, 'invoice_dat' => $corporate_vehicles_quotes_request->vehicle_invoice_date],
                        'new_date' => ['manf_dat' => $manufactureYear, 'reg_dat' => $registrationDate, 'invoice_dat' => $invoiceDate],
                    ]);

                    $corporate_vehicles_quotes_request->update([
                        'manufacture_year' => $manufactureYear,
                        'vehicle_register_date' => $registrationDate,
                        'vehicle_invoice_date' => $invoiceDate
                    ]);
                }
            }


            if ($proposal) {
                $additional_details = json_decode($proposal->additional_details, true) ?? [];

                if (
                    ($proposal->vehicle_manf_year !== $manufactureYear) ||
                    ($additional_details['vehicle']['vehicleManfYear'] ?? '') !== $manufactureYear ||
                    ($additional_details['vehicle']['registrationDate'] ?? '') !== $registrationDate
                ) {
                    $proposal->vehicle_manf_year = $manufactureYear;
                    $additional_details['vehicle']['vehicleManfYear'] = $manufactureYear;
                    $additional_details['vehicle']['registrationDate'] = $registrationDate;

                    $proposal->update([
                        'vehicle_manf_year' => $manufactureYear,
                        'additional_details' => json_encode($additional_details)
                    ]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Date Updated successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Date modification not allow for stage ' . $journey_stage]);
        }
    }
}
