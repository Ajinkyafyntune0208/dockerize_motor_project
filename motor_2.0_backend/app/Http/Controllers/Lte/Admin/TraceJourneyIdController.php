<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\UserProposal;

class TraceJourneyIdController extends Controller
{

    public function index(Request $request)
    {

        if (!auth()->user()->can('get_trace_id.list')){
           abort(403, 'Unauthorized action.');
        }
        if ($request->userInput != null) {
            $request->validate(['userInput' => ['required']]);
        }

        $traceIdDetails = array();
        $data = array();

        if ($request->userInput != null) {

            $userInput = str_replace('-', '', $request->userInput);

            try {

                switch ($request->type) {
                    case 'rcNumber':
                        $withHyphen = getRegisterNumberWithHyphen($userInput);
                        $rcNumberArray = [
                            $userInput,
                            $request->userInput,
                            $withHyphen
                        ];
                        $withHyphen = explode('-', $withHyphen);
                        if (isset($withHyphen[0], $withHyphen[1])) {
                            $rtoCode = RtoCodeWithOrWithoutZero(implode('-', [$withHyphen[0], $withHyphen[1]]));
                            $rtoCode =explode('-', $rtoCode);
                            $withHyphen[0] = $rtoCode[0] ?? $withHyphen[0];
                            $withHyphen[1] = $rtoCode[1] ?? $withHyphen[1];
                        }
                        $withHyphen = implode('-', $withHyphen);
                        array_push($rcNumberArray, $withHyphen);

                        $withHyphen = explode('-', $withHyphen);
                        if (
                            isset($withHyphen[1]) &&
                            is_numeric($withHyphen[1]) &&
                            $withHyphen[1] < 10 &&
                            strlen(($withHyphen[2] ?? '')) > 1
                        ) {
                            $withHyphen[1] = $withHyphen[1] * 1;
                            $withHyphen[1] .= substr($withHyphen[2], 0, 1);
                            $withHyphen[2] = substr($withHyphen[2], 1);
                        }
                        $withHyphen = implode('-', $withHyphen);
                        array_push($rcNumberArray, $withHyphen);
                        $rcNumberArray = array_unique($rcNumberArray);

                        $traceIdDetails = DB::table('user_proposal as l')
                            ->selectRaw("CONCAT(DATE_FORMAT(j.created_on,'%Y%m%d'),LPAD(j.user_product_journey_id,8,0)) AS `TraceID`,
                                 l.vehicale_registration_number AS `RCNumber`,
                                 payment.status AS `status`,
                                 br.breakin_number as breakinNumber,
                                 policy.policy_number AS `policyNumber`,
                                 policy.pdf_url AS `pdfURL`")
                            ->join('user_product_journey as j', 'j.user_product_journey_id', '=', 'l.user_product_journey_id')
                            ->leftJoin('payment_request_response as payment', function ($join) {
                                $join->on('l.user_product_journey_id', '=', 'payment.user_product_journey_id')
                                    ->where('payment.active', '=', '1');
                            })
                            ->leftJoin('cv_breakin_status as br', 'br.user_proposal_id', '=', 'l.user_proposal_id')
                            ->leftJoin('policy_details as policy', 'payment.user_proposal_id', '=', 'policy.proposal_id')
                            // ->whereRaw("REPLACE(l.vehicale_registration_number,'-','') = ? ", [$userInput])
                            ->whereIn('vehicale_registration_number', $rcNumberArray)
                            ->orderBy('j.user_product_journey_id')
                            ->get();
                        break;
                    case 'breakin_policy_number':
                        $data = UserProposal::select('user_product_journey_id')
                            ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
                            ->where(DB::raw('trim(pd.policy_number)'), '=', request()->userInput)
                            ->limit(1)
                            ->union(
                                UserProposal::select('user_product_journey_id')
                                    ->join('cv_breakin_status as cbs', 'cbs.user_proposal_id', '=', 'user_proposal.user_proposal_id')
                                    ->where(DB::raw('trim(cbs.breakin_number)'), '=', request()->userInput)
                                    ->limit(1)
                            )
                            ->limit(1)
                            ->first();

                        if (!empty($data['user_product_journey_id'])) {
                            $data['encrypted_journey_id'] = customEncrypt($data['user_product_journey_id']);
                        }
                        break;
                    case 'engineNo':
                        $traceIdDetails = DB::table('user_proposal as l')
                            ->selectRaw("CONCAT(DATE_FORMAT(j.created_on,'%Y%m%d'),LPAD(j.user_product_journey_id,8,0)) AS `TraceID`,
                                 l.vehicale_registration_number AS `RCNumber`,
                                 payment.status AS `status`,
                                 br.breakin_number as breakinNumber,
                                 policy.policy_number AS `policyNumber`,
                                 policy.pdf_url AS `pdfURL`")
                            ->join('user_product_journey as j', 'j.user_product_journey_id', '=', 'l.user_product_journey_id')
                            ->leftJoin('payment_request_response as payment', function ($join) {
                                $join->on('l.user_product_journey_id', '=', 'payment.user_product_journey_id')
                                    ->where('payment.active', '=', '1');
                            })
                            ->leftJoin('cv_breakin_status as br', 'br.user_proposal_id', '=', 'l.user_proposal_id')
                            ->leftJoin('policy_details as policy', 'payment.user_proposal_id', '=', 'policy.proposal_id')
                            // ->whereRaw("UPPER(l.engine_number) = ? ", [strtoupper($userInput)])
                            ->where("l.engine_number", '=', $userInput)
                            ->orderBy('j.user_product_journey_id')
                            ->get();
                        break;
                    case 'chassisNo':
                        $traceIdDetails = DB::table('user_proposal as l')
                            ->selectRaw("CONCAT(DATE_FORMAT(j.created_on,'%Y%m%d'),LPAD(j.user_product_journey_id,8,0)) AS `TraceID`,
                                 l.vehicale_registration_number AS `RCNumber`,
                                 payment.status AS `status`,
                                 br.breakin_number as breakinNumber,
                                 policy.policy_number AS `policyNumber`,
                                 policy.pdf_url AS `pdfURL`")
                            ->join('user_product_journey as j', 'j.user_product_journey_id', '=', 'l.user_product_journey_id')
                            ->leftJoin('payment_request_response as payment', function ($join) {
                                $join->on('l.user_product_journey_id', '=', 'payment.user_product_journey_id')
                                    ->where('payment.active', '=', '1');
                            })
                            ->leftJoin('cv_breakin_status as br', 'br.user_proposal_id', '=', 'l.user_proposal_id')
                            ->leftJoin('policy_details as policy', 'payment.user_proposal_id', '=', 'policy.proposal_id')
                            // ->whereRaw("UPPER(l.chassis_number) = ? ", [strtoupper($userInput)])
                            ->where("l.chassis_number", '=', $userInput)
                            ->orderBy('j.user_product_journey_id')
                            ->get();
                        break;
                }

                

            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Sorry, Something Wents Wrong !',
                    'class' => 'danger',
                ]);
            }
        }

        $options = [
            'rcNumber' => 'RC Number',
            'breakin_policy_number' =>'Breakin ID/Policy Number',
            'engineNo' => 'Engine Number',
            'chassisNo' => 'Chassis Number'
        ];

        return view('admin_lte.trace-journey-id.index', compact('traceIdDetails', 'data', 'options'));
    }

}
