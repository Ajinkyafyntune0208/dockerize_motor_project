<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PolicyReport;
use App\Models\TemplateModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class PolicyReportMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generatePolicyReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy Report Generate';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Policy Details Report Started on : ' . Carbon::now()->setTimezone('Asia/Kolkata')->format('Y-m-d h:i:s'));
        $today = Carbon::now()->setTimezone('Asia/Kolkata')->format('Y-m-d');
        $previous_date = Carbon::yesterday()->setTimezone('Asia/Kolkata')->format('Y-m-d');
        $current_month = Carbon::now()->startOfMonth()->setTimezone('Asia/Kolkata')->format('Y-m-d');
        $current_year = Carbon::now()->startOfYear()->setTimezone('Asia/Kolkata')->format('Y-m-d');

        $previous_date_reports = DB::table('user_product_journey as up')
        ->leftJoin('cv_journey_stages as js', 'js.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('payment_request_response as pr', 'pr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('corporate_vehicles_quotes_request as cvr', 'cvr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('cv_agent_mappings as am', 'am.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('master_product_sub_type as pst', 'cvr.product_id', '=', 'pst.product_sub_type_id')
        ->leftJoin('user_proposal as pro', 'pro.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('policy_details as pol', 'pol.proposal_id', '=', 'pro.user_proposal_id')
        ->leftJoin('quote_log as ql', 'ql.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->select('pst.product_sub_type_code', DB::raw('count(pst.product_sub_type_id) as count_of_policy'), DB::raw('sum(ql.final_premium_amount) as premium_amount'))
        ->groupBy('pst.product_sub_type_code')->whereIn('js.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']])
        ->whereDate('js.updated_at', $previous_date)->get();

    $current_month_reports = DB::table('user_product_journey as up')
        ->leftJoin('cv_journey_stages as js', 'js.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('payment_request_response as pr', 'pr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('corporate_vehicles_quotes_request as cvr', 'cvr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('cv_agent_mappings as am', 'am.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('master_product_sub_type as pst', 'cvr.product_id', '=', 'pst.product_sub_type_id')
        ->leftJoin('user_proposal as pro', 'pro.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('policy_details as pol', 'pol.proposal_id', '=', 'pro.user_proposal_id')
        ->leftJoin('quote_log as ql', 'ql.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->select('pst.product_sub_type_code', DB::raw('count(pst.product_sub_type_id) as count_of_policy'), DB::raw('sum(ql.final_premium_amount) as premium_amount'))
        ->groupBy('pst.product_sub_type_code')->whereIn('js.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']])
        ->whereBetween('js.updated_at', [$current_month, $today])->get();

    $current_year_reports = DB::table('user_product_journey as up')
        ->leftJoin('cv_journey_stages as js', 'js.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('payment_request_response as pr', 'pr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('corporate_vehicles_quotes_request as cvr', 'cvr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('cv_agent_mappings as am', 'am.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('master_product_sub_type as pst', 'cvr.product_id', '=', 'pst.product_sub_type_id')
        ->leftJoin('user_proposal as pro', 'pro.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('policy_details as pol', 'pol.proposal_id', '=', 'pro.user_proposal_id')
        ->leftJoin('quote_log as ql', 'ql.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->select('pst.product_sub_type_code', DB::raw('count(pst.product_sub_type_id) as count_of_policy'), DB::raw('sum(ql.final_premium_amount) as premium_amount'))
        ->groupBy('pst.product_sub_type_code')->whereIn('js.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']])
        ->whereBetween('js.updated_at', [$current_year, $today])->get();
        
        $template = '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid;">
        <tbody>';
        if(count($previous_date_reports) > 0){
        $template = $template . '<tr>
                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                        <thead>
                            <tr>
                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px "Lato","Helvetica Neue",Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                    Number of Policy issued on '.$previous_date.'
                                </th>
                            </tr>

                            <tr>
                                <th style="text-align:center; color:white">Product</th>
                                <th style="text-align:center; color:white">Count Of Policy</th>
                                <th style="text-align:center; color:white">Premium(Rs)</th>
                            </tr>
                        </thead>
                        <tbody>';

                            foreach($previous_date_reports as $report){
                            $template = $template .
                                '<tr>
                                    <td style="color:white">
                                        <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->product_sub_type_code.'</p>
                                    </td>
                                    <td style="color:white">
                                        <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->count_of_policy.'</p>
                                    </td>
                                    <td style="color:white">
                                        <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.number_format($report->premium_amount,2).'</p>
                                    </td>
                                </tr>';
                            }
                            $template = $template .'</tbody>
                    </table>
                </td>
            </tr>';
            }

            if(count($current_month_reports) > 0){
            $template = $template. '<tr>
                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                        <thead>
                            <tr>
                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px "Lato","Helvetica Neue",Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                    Number of Policy issued on '.$current_month.' - '.$today.'
                                </th>
                            </tr>

                            <tr>
                                <th style="text-align:center">Product</th>
                                <th style="text-align:center">Count Of Policy</th>
                                <th style="text-align:center">Premium(Rs)</th>
                            </tr>
                        </thead>
                        <tbody>';

                            foreach($current_month_reports as $report){
                            $template = $template. '<tr>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->product_sub_type_code.'</p>
                                </td>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->count_of_policy.'</p>
                                </td>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.number_format($report->premium_amount,2).'</p>
                                </td>
                            </tr>';
                            }
                            $template = $template.'</tbody>
                    </table>
                </td>
            </tr>';
            }

            if(count($current_year_reports) > 0){
            $template = $template.'<tr>
                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                        <thead>
                            <tr>
                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px "Lato","Helvetica Neue",Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                    Number of Policy issued on '.$current_year.' - '.$today.'
                                </th>
                            </tr>

                            <tr>
                                <th style="text-align:center">Product</th>
                                <th style="text-align:center">Count Of Policy</th>
                                <th style="text-align:center">Premium(Rs)</th>
                            </tr>
                        </thead>
                        <tbody>';

                            foreach($current_year_reports as $report){
                            $template = $template. '<tr>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->product_sub_type_code.'</p>
                                </td>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.$report->count_of_policy.'</p>
                                </td>
                                <td style="color:white">
                                    <p style="display:block; text-decoration:none; border:0; text-align:center; color:blue">'.number_format($report->premium_amount,2).'</p>
                                </td>
                            </tr>';
                            }
                            $template = $template. '</tbody>
                    </table>
                </td>
            </tr>';
                        }
                        $template = $template.'<tr>
                <td style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
                    <div style="width:80%; padding:10px 0 10px; margin:0 auto;">
                        CIN : U74140DL2015PTC276540 Compare Policy Insurance Web
                        Aggregators Pvt Ltd. IRDAI Web Aggregator Registration
                        No. 010, License Code No IRDAI/WBA23/15
                    </div>
                </td>
            </tr>
        </tbody>
    </table>';

        $final_template = TemplateModel::select('to','bcc','content')->where('alias','policy_report')->first();
        $result  = str_replace("{@content}",$template,$final_template->content);
        unset($today, $current_month, $current_year, $previous_date, $template, $previous_date_reports, $current_month_reports, $current_year_reports, $report);
        Mail::to($final_template->to) ->bcc($final_template->bcc)->send(new PolicyReport($result));
        Log::info('Policy Details Report sent on : ' . Carbon::now()->setTimezone('Asia/Kolkata')->format('Y-m-d h:i:s'));
        return 0;
    }
}
