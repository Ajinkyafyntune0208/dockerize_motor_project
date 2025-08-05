<?php

namespace App\Providers;

use App\Models\CkycVerificationTypes;
use App\Models\MasterPolicy;
use App\Models\ConfigSetting;
use App\Models\CommonConfigurations;
use App\Models\PasswordPolicy;
use App\Models\User;
use App\Models\ProposalValidation;
use App\Models\ThirdPartySetting;
use App\Observers\ConfigObserver;
use App\Observers\CommonConfigurationObserver;
use App\Observers\MasterPolicyLogObserver;
use App\Observers\ProposalValidationObserver;
use App\Observers\UserObserver;
use App\Observers\ThirdPartySettingObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Observers\CkycVerificationObserver;
use App\Observers\PasswordPolicyObserver;
use PhpOffice\PhpSpreadsheet\Shared\PasswordHasher;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    //public function boot()
    //{
        //ConfigSetting::observe(ConfigObserver::class);
        //User::observe(UserObserver::class);
        //ProposalValidation::observe(ProposalValidationObserver::class);
        //ThirdPartySetting::observe(ThirdPartySettingObserver::class);
        //MasterPolicy::observe(MasterPolicyLogObserver::class);
        //CkycVerificationTypes::observe(CkycVerificationObserver::class);       
        //PasswordPolicy::observe(PasswordPolicyObserver::class);       
    //}
}
