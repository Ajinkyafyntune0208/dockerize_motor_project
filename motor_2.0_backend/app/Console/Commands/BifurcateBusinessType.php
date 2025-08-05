<?php

namespace App\Console\Commands;

use App\Models\MasterPolicy;
use App\Models\MasterProduct;
use App\Models\QuoteLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BifurcateBusinessType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bifurcate:businessTypes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bifurcates the business types';

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
        DB::beginTransaction();
        try {
            $master_policy_list = MasterPolicy::with('master_product')
            ->orderBy('policy_id', 'DESC')
            ->get()
            ->toArray();
            foreach ($master_policy_list as $policy) {
                $business_type = explode(',', $policy['business_type']);
                if (count($business_type) >= 2 && !empty($policy['master_product'])) {

                    if (($index = array_search('rollover', $business_type)) !== false) {
                        array_splice($business_type, $index, 1);
                        MasterPolicy::where('policy_id', $policy['policy_id'])->update([
                            'business_type' => 'rollover'
                        ]);
                    } else {
                        MasterPolicy::where('policy_id', $policy['policy_id'])->update([
                            'business_type' => $business_type[0]
                        ]);
                        unset($business_type[0]);
                    }
                    foreach ($business_type as $b) {
                        $product_copy = $policy['master_product'];
                        $policy_copy = $policy;
                        unset(
                            $product_copy['created_at'],
                            $product_copy['updated_at'],
                            $product_copy['product_id']
                        );
                        unset(
                            $policy_copy['policy_id'],
                            $policy_copy['master_product'],
                            $policy_copy['created_date'],
                            $policy_copy['updated_date']
                        );
                        $policy_copy['business_type'] = $b;
                        $result = MasterPolicy::create($policy_copy);
                        $product_copy['master_policy_id'] = $result->policy_id;
                        $result = MasterProduct::create($product_copy);
                        QuoteLog::join(
                            'corporate_vehicles_quotes_request as corp',
                            'corp.user_product_journey_id',
                            '=',
                            'quote_log.user_product_journey_id'
                        )
                            ->where([
                                'quote_log.master_policy_id' => $policy['policy_id'],
                                'corp.business_type' => $b
                            ])->update([
                                'master_policy_id' => $product_copy['master_policy_id']
                            ]);
                    }
                }
            }
            DB::commit();
            $this->info("Business types bifurcated successfully....");
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->info("Error :". $th->getMessage());
        }
    }
}
