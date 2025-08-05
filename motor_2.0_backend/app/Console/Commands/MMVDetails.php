<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ManufacturerTopFive;
use App\Models\UserProposal;

class MMVDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manuf_mmv:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $user_proposal = UserProposal::join('corporate_vehicles_quotes_request', 'corporate_vehicles_quotes_request.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')->join('policy_details', 'policy_details.proposal_id', '=', 'user_proposal.user_proposal_id')->select('corporate_vehicles_quotes_request.version_id', 'corporate_vehicles_quotes_request.version_id', 'corporate_vehicles_quotes_request.product_id','user_proposal.city_id','user_proposal.state_id')->groupBy('corporate_vehicles_quotes_request.version_id', 'corporate_vehicles_quotes_request.version_id', 'corporate_vehicles_quotes_request.product_id')->selectRaw('policy_details.policy_number, COUNT(policy_details.policy_number) as policy_count')->orderByDesc('policy_count')->take(config('constants.motorConstant.MANUFACTURER_AUTOLOAD_PRIORITY',5))->get();
        foreach ($user_proposal as $key => $value) {
            $manf_count = ManufacturerTopFive::where('city_id', $value['city_id'])->where('state_id', $value['state_id'])->where('version_id', $value['version_id'])->count();
            if ($manf_count == 0) {
                $manf = new ManufacturerTopFive;
                $manf->city_id = $value['city_id'];
                $manf->product_id = $value['product_id'];
                $manf->state_id = $value['state_id'];
                $manf->version_id = $value['version_id'];
                $manf->policy_count = $value['policy_count'];
                $manf->save();   
            }
        }
    }
}
