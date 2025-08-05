<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\Payment\Services\Car;
use App\Http\Controllers\Payment\Services\bike;
use App\Http\Controllers\Payment\Services\Bike\iffco_tokioPaymentGateway as BikeIffco_tokioPaymentGateway;
use App\Http\Controllers\Payment\Services\Car\iffco_tokioPaymentGateway;

use App\Http\Controllers\Payment\Services\iffco_tokioPaymentGateway as CV_IFFCO;
use App\Http\Controllers\Payment\Services\IffcoTokioshortTermPaymentGateway as CV_IFFCO_SHORTTERM;

class GeneratePDFJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $user_proposal = null;
    protected $section = null;
    protected $company_alias = null;
    public function __construct($company_alias,$section,$user_proposal)
    {
        $this->company_alias = $company_alias;
        $this->section =  $section;
        $this->user_proposal = $user_proposal;    
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        switch ($this->section) {
            case 'car':
                require_once app_path().'/Helpers/CarWebServiceHelper.php';
                switch ($this->company_alias) {
                    case 'iffco_tokio':
                        $generate_pdf = iffco_tokioPaymentGateway::generate_pdf($this->user_proposal)->getOriginalContent();
                        break;
                    
                    default:
                        # code...
                        break;
                }
                break;
            
            case 'bike':
                require_once app_path().'/Helpers/BikeWebServiceHelper.php';
                switch ($this->company_alias) {
                    case 'iffco_tokio':
                        $generate_pdf = BikeIffco_tokioPaymentGateway::generate_pdf($this->user_proposal)->getOriginalContent();
                        break;
                    
                    default:
                        # code...
                        break;
                }
                break;

            case 'cv':
                switch ($this->company_alias) {
                    case 'iffco_tokio':
                        $generate_pdf = CV_IFFCO::generate_pdf($this->user_proposal)->getOriginalContent();
                        break;
                    
                    default:
                        # code...
                        break;
                }
                break;

            case 'cv_short_term':
                switch ($this->company_alias) {
                    case 'iffco_tokio':
                        $generate_pdf = CV_IFFCO_SHORTTERM::generate_pdf($this->user_proposal)->getOriginalContent();
                        break;
                    
                    default:
                        # code...
                        break;
                }
                break;
            default:
                # code...
                break;
        }
    }
}
