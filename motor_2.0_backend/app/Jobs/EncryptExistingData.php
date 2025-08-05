<?php

namespace App\Jobs;

use App\Models\CkycLogsRequestResponse;
use App\Models\ckycRequestResponse;
use App\Models\QuoteServiceRequestResponse;
use App\Models\UserProposal;
use App\Models\WebServiceRequestResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class EncryptExistingData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $offset;
    public $limit;
    public $table;
    public $action;
    public $count;

    public function __construct(
        $action,
        $offset = null,
        $limit = null,
        $table = null,
        $count = 0
    ) {
        $this->offset = $offset;
        $this->limit = $limit;
        $this->table = $table;
        $this->action = $action;
        $this->count = $count;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($this->action) {
            case 'fetch':
                self::fetch();
                break;
            case 'process':
                self::process($this->table, $this->count);
                break;
            case 'update':
                self::update($this->limit, $this->offset, $this->table);
                break;
            default:
                # code...
                break;
        }
    }

    public static function fetch()
    {
        $queueName = config('constants.brokerConstant.ENCRYPTION_QUEUE_NAME');
        $count = UserProposal::max('user_proposal_id');
        EncryptExistingData::dispatch('process', null, null, 'user_proposal', $count)->onQueue($queueName);

        $count = ckycRequestResponse::max('id');
        EncryptExistingData::dispatch('process', null, null, 'ckyc_request_responses', $count)->onQueue($queueName);


        $count = WebServiceRequestResponse::max('id');
        EncryptExistingData::dispatch('process', null, null, 'webservice_request_response_data', $count)->onQueue($queueName);

        $count = QuoteServiceRequestResponse::max('id');
        EncryptExistingData::dispatch('process', null, null, 'quote_webservice_request_response_data', $count)->onQueue($queueName);

        $count = CkycLogsRequestResponse::max('id');
        EncryptExistingData::dispatch('process', null, null, 'ckyc_logs_request_responses', $count)->onQueue($queueName);
    }

    public static function update($limit, $offset, $table)
    {
        switch ($table) {
            case 'user_proposal':
                $data = UserProposal::limit($limit)
                    ->offset($offset)
                    ->get()
                    ->toArray();
                foreach ($data as $value) {
                    $id = $value['user_proposal_id'];
                    $updated_at = $value['updated_at'];
                    unset($value['user_proposal_id'], $value['additonal_data'], $value['updated_at']);
                    UserProposal::where('user_proposal_id', $id)->update($value);

                    if (!empty($updated_at)) {
                        DB::table($table)->where('user_proposal_id', $id)->update([
                            'updated_at' => date('Y-m-d H:i:s', strtotime($updated_at))
                        ]);
                    }
                }
                break;

            case 'ckyc_request_responses':
                $data = ckycRequestResponse::limit($limit)
                    ->offset($offset)
                    ->get()
                    ->toArray();

                foreach ($data as $value) {
                    $id = $value['id'];
                    $updated_at = $value['updated_at'];
                    unset($value['id']);

                    ckycRequestResponse::where('id', $id)
                    ->update($value);
                    
                    if (!empty($updated_at)) {
                        DB::table($table)
                        ->where('id', $id)
                        ->update([
                            'updated_at' => date('Y-m-d H:i:s', strtotime($updated_at))
                        ]);
                    }
                }
                break;

            case 'webservice_request_response_data':
                $data = WebServiceRequestResponse::limit($limit)
                    ->offset($offset)
                    ->get()
                    ->toArray();

                foreach ($data as $value) {
                    $id = $value['id'];
                    unset($value['id']);

                    WebServiceRequestResponse::where('id', $id)
                    ->update($value);
                }
                break;

            case 'ckyc_logs_request_responses':
                $data = CkycLogsRequestResponse::limit($limit)
                    ->offset($offset)
                    ->get()
                    ->toArray();

                foreach ($data as $value) {
                    $id = $value['id'];
                    $updated_at = $value['updated_at'];
                    unset($value['id']);
                    
                    CkycLogsRequestResponse::where('id', $id)
                    ->update($value);

                    if (!empty($updated_at)) {
                        DB::table($table)->where('id', $id)
                        ->update([
                            'updated_at' => date('Y-m-d H:i:s', strtotime($updated_at))
                        ]);
                    }
                }
                break;

            case 'quote_webservice_request_response_data':
                $data = QuoteServiceRequestResponse::limit($limit)
                    ->offset($offset)
                    ->get()
                    ->toArray();

                foreach ($data as $value) {
                    $id = $value['id'];
                    unset($value['id']);

                    QuoteServiceRequestResponse::where('id', $id)
                    ->update($value);
                }
                break;

            default:
                break;
        }
    }

    public static function process($table, $count)
    {
        $queueName = config('constants.brokerConstant.ENCRYPTION_QUEUE_NAME');
        $i = 0;
        $limit = config('constants.CKYC_EXISTING_ENCRYPTION_LIMIT', 500);
        while ($i < $count) {
            $offset = $i;
            $i = $i + $limit;
            EncryptExistingData::dispatch('update', $offset, $limit, $table)->onQueue($queueName);
        }
    }
}
