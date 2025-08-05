<?php

use App\Http\Controllers\Admin\{
    DiscountConfigurationController,
    RenewalDataMigrationStatusController,
    UserJourneyActivityController,
    DataPushResReqLogController,
    RenewalUploadExcelController,
    SqlRunnerController,
    DashboardMongoLogsController,
    TemplateMasterController,
};
use App\Http\Controllers\Lte\Admin\CommisionConfiguratorController;
use App\Http\Controllers\Admin\IcConfiguratorController;
use App\Http\Controllers\Lte\Admin\CommunicationConfigurationController;
use App\Http\Controllers\Lte\Admin\ConfiguredIcController;
use App\Http\Controllers\CommonController;
use App\Http\Controllers\HidePyiController;
use App\Http\Controllers\Lte\Admin\AttributeController;
use App\Http\Controllers\LogRotationController;
use App\Http\Controllers\Lte\Admin\MasterProductTypeController;
use App\Http\Controllers\MmvBlockerController;
use Illuminate\Support\Facades\Route;
use App\Models\SelfInspectionAppDetail;
use App\Http\Controllers\sbi\SbiApiRequestController;
use App\Http\Controllers\ProposalValidation;
use App\Jobs\RenewalMigrationProcessSingle;
use App\Models\HidePyi;
use App\Models\InspectionType;
use Illuminate\Http\Request;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    try {
      if (!empty(request()->all())) {
          $url = \Illuminate\Support\Facades\Storage::exists(customDecrypt(\Illuminate\Support\Arr::first(request()->all()), false));
          $file = \Illuminate\Support\Facades\Storage::path(customDecrypt(\Illuminate\Support\Arr::first(request()->all()), false));
          if (!$url) {
              abort(404);
          }
          $contentType = mime_content_type($file);
          if (in_array($contentType, ['application/octet-stream'])) {
              $contentType = 'application/pdf';
          }
          return response()->file($file, ['Content-Type' => $contentType]);
      }
  } catch (\Exception $e) {
      abort(403);
  }
  abort(403);
  return view('welcome');
})->name('home');

//*--------------------------Password Policy Routes--------------------------
Route::get('/reset-password',[\App\Http\Controllers\Admin\ResetPasswordController::class,'resetPassword'])->name('reset-password');
Route::get('/reset-password-login-page/{id}',[\App\Http\Controllers\Admin\ResetPasswordController::class,'index'])->name('reset-password-login-page');
Route::put('/update-reset-password/{id}',[\App\Http\Controllers\Admin\UserController::class,'updateResetPassword'])->name('update-reset-password');
Route::get('/success',[\App\Http\Controllers\Admin\UserController::class,'success'])->name('success');
//*-----------------------------------------------------------
Route::get('self-inspection-list', function () {
    return view('sample', ['self_inspection_list' => SelfInspectionAppDetail::all()]);
});

Route::get('run-job/{jobName}', function ($jobName) {
    $jobName = str_replace("/", "", "\App\Jobs\/" . $jobName);
    if(class_exists($jobName)){
        $jobName::dispatchSync();
        return "Process";
    } else {
        abort(404);
    }
});
Route::get('/sbi-policy/{policy_number}', [SbiApiRequestController::class, 'policyGeneration']);
Route::get('/newSBIPolicyGeneration/{policy_number}', [SbiApiRequestController::class, 'newPolicyGeneration']);


# For Testing Purpose.
Route::get('/bclRenewalUploads', function () {
    if (config('constants.brokerConstant.ENABLE_RENEWAL_ATTEMPT_LOGIC') == 'Y') {
        return response()->json([
            'message' => 'Not Allowed'
        ]);
    }
    \App\Jobs\RenewalMigrationProcessSingle::dispatch();
});
Route::get('/RenewalVehiclePremium', function () {
    \App\Jobs\RenewalVehiclePremium::dispatchSync();
});
Route::match(['get', 'post'], 'cv/payment-confirm/{ic_name}', [App\Http\Controllers\Payment\PaymentController::class, 'confirm'])->name('cv.payment-confirm');
Route::match(['get', 'post'], 'car/payment-confirm/{ic_name}', [App\Http\Controllers\Payment\CarPaymentController::class, 'confirm'])->name('car.payment-confirm');
Route::match(['get', 'post'], 'bike/payment-confirm/{ic_name}', [App\Http\Controllers\Payment\BikePaymentController::class, 'confirm'])->name('bike.payment-confirm');

Route::get('admin/login', [\App\Http\Controllers\Admin\LoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('admin/login', [\App\Http\Controllers\Admin\LoginController::class, 'authenticate']);
Route::post('admin/logout', [\App\Http\Controllers\Admin\LoginController::class, 'logout'])->name('admin.logout');
Route::get('admin/verify_otp', [\App\Http\Controllers\Admin\LoginController::class, 'showOtpForm'])->name('admin.otp');
Route::get('admin/verify_totp', [\App\Http\Controllers\Admin\LoginController::class, 'showTOtpForm'])->name('admin.totp');
Route::post('admin/verify_otp', [\App\Http\Controllers\Admin\LoginController::class, 'verifyOtp']);
Route::post('admin/verify_totp', [\App\Http\Controllers\Admin\LoginController::class, 'verifyTOtp']);
Route::get('admin/request_email', [\App\Http\Controllers\Admin\UserController::class, 'showEmailIdForm'])->name('admin.emailRequest');
Route::post('admin/request_email', [\App\Http\Controllers\Admin\UserController::class, 'resendQrCode']);
Route::post('admin/send_email', [\App\Http\Controllers\Admin\UserController::class, 'sendEmail'])->name('admin.send_email');
Route::post('/admin/update_password', [\App\Http\Controllers\Admin\UserController::class, 'updatePassword'])->name('password.update');
Route::get('admin/reset_password', [\App\Http\Controllers\Admin\LoginController::class, 'ResetPassword'])->name('reset_password');

Route::get('admin/verify_totp_forget_password', [\App\Http\Controllers\Admin\LoginController::class, 'showTOtpFormForgetPassoword']);
Route::post('admin/verify_totp_forget_password', [\App\Http\Controllers\Admin\LoginController::class, 'verifyTOTPForgetPassoword']);
Route::post('admin/check-otp-type', [\App\Http\Controllers\Admin\LoginController::class, 'checkOtpType'])->name('check.otp.type');
// Route::get('app-log', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);
Route::get('push-api/view/{id}', [\App\Http\Controllers\Admin\PushApiController::class, 'viewRequestResponse'])->name('admin.push-api.viewviewRequestResponse');
Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => 'auth'
], function () {
    Route::get('sync_mmv', function () {
        return view('mmv_sync.index');
    })->name('sync_mmv');
    Route::get('sync_rto', function () {
        return view('rto_sync.index');
    });
    Route::get('rto_sync', function () {
        return view('admin_lte.rto_sync.index');
    })->name('sync_rto');
    Route::get('sync_rto', function () {
        return view('rto_sync.index');
    });
    Route::get('/', function () {
        return redirect('admin/dashboard');
    });
    Route::resource('renewal-data-migration', RenewalDataMigrationStatusController::class);
    Route::get('renewal-data-migration/download/{id}',[RenewalDataMigrationStatusController::class, 'download'])->name('renewal-data-migration.download');

    Route::match(['get', 'post'], 'user-journey-activity', [UserJourneyActivityController::class, 'index'])->name('user-journey-activity');
    Route::group([
        'prefix' => 'discount-configurations',
        'as' => 'discount-configurations.',
    ], function () {
        Route::match(['get', 'post'], 'config-setting', [DiscountConfigurationController::class, 'configSetting'])->name('config-setting');
        Route::match(['get', 'post'], 'global-config', [DiscountConfigurationController::class, 'globalConfig'])->name('global-config');
        Route::match(['get', 'post'], 'vehicle-config', [DiscountConfigurationController::class, 'vehicleConfig'])->name('vehicle-config');
        Route::match(['get', 'post'], 'ic-config', [DiscountConfigurationController::class, 'icConfig'])->name('ic-config');
        Route::match(['get', 'post'], 'active-config', [DiscountConfigurationController::class, 'activeConfig'])->name('active-config');
        Route::post('validate-ic', [DiscountConfigurationController::class, 'validateIcs'])->name('validate-ics');

        Route::get('activity-logs', [DiscountConfigurationController::class, 'activityLogs'])->name('activity-logs');
    });
    Route::post('addProposalValidation', [ProposalValidation::class, 'addProposalValidation'])->name("addProposalValidation");
    // Route::get('vahan-service-activity-logs', [\App\Http\Controllers\Admin\VahanServiceController::class, 'activityLog'])->name('vahan.activityLogs');
    Route::resource('user-activity-logs', \App\Http\Controllers\Admin\UserActivityLogsController::class);
    
    Route::get('common-config', [\App\Http\Controllers\Admin\CommonConfigurationsController::class, 'index'])->name('common-config');
    Route::post('common-config-save', [\App\Http\Controllers\Admin\CommonConfigurationsController::class, 'save'])->name('common-config-save');
    Route::post('common-config-mongo', [\App\Http\Controllers\Admin\CommonConfigurationsController::class, 'saveMongoConfig'])->name('common-config-mongo');
    Route::resource('vahan_service', \App\Http\Controllers\Admin\VahanServiceController::class);
    Route::post('vahan_configurator',[\App\Http\Controllers\Admin\VahanServiceController::class, 'VahanConfigurator'])->name('vahan_configurator');
    Route::post('cred_insert', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credInsert'])->name('cred_insert.insert');
    Route::delete('cred_delete', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credDelete'])->name('cred_delete.delete');
    Route::post('cred_update', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credUpdate'])->name('cred_update.update');
    Route::post('cred_keyCheck', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credKeyCheck'])->name('cred_keyCheck.check');
    Route::post('vahan_service_save/{v_type}', [\App\Http\Controllers\Admin\VahanServiceController::class, 'prioritySave'])->name('vahan_service_save.prioritySave');
    Route::get('vahan_service_dash', [\App\Http\Controllers\Admin\VahanServiceController::class, 'dash'])->name('vahan_service_dash.dash');
    Route::get('vahan-service-credentials', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credData'])->name('vahan-service-credentials.index');
    Route::get('vahan-service-stage', [\App\Http\Controllers\Admin\VahanServiceController::class, 'stageIndex'])->name('vahan-service-stage.stageIndex');
    Route::get('vahan-service-stage-edit/{key}/{v_type}', [\App\Http\Controllers\Admin\VahanServiceController::class, 'stageEdit'])->name('vahan-service-stage-edit.stageEdit');
    Route::get('vahan_credentials_read/{id}', [\App\Http\Controllers\Admin\VahanServiceController::class, 'credCrudPage'])->name('vahan_credentials_read.crud');
    Route::delete('vahan_credentials/{id?}/{parameter?}', [\App\Http\Controllers\Admin\VahanServiceController::class, 'destroy'])->name('vahan_credentials.delete');
    Route::resource('dashboard', \App\Http\Controllers\Admin\DashboardController::class);
    Route::get('journey-data', [\App\Http\Controllers\Admin\DashboardController::class, 'getJourneyData'])->name('journey-data.index');
    Route::get('abibl-data-migration', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigration'])->name('abibl-data-migration.index');
    Route::get('abibl-data-migration-old', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigrationOld'])->name('abibl-data-migration-old.index');
    Route::post('abibl-data-migration', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigrationStore'])->name('abibl-data-migration.store');
    Route::post('abibl-data-migration-old', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigrationOldStore'])->name('abibl-data-migration-old.store');

    Route::get('abibl-data-migration-hyundai', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigrationHyundai'])->name('abibl-data-migration-hyundai.index');
    Route::post('abibl-data-migration-hyundai', [\App\Http\Controllers\Admin\DashboardController::class, 'abiblDataMigrationHyundaiStore'])->name('abibl-data-migration-hyundai.store');

    Route::resource('configuration', \App\Http\Controllers\Admin\ConfigurationController::class);
    Route::resource('master_policy', \App\Http\Controllers\Admin\MasterPolicyController::class);
    Route::post('masterproduct-statusupdate', [\App\Http\Controllers\Admin\MasterPolicyController::class,'statusUpdate'])->name('masterproduct-statusupdate');
    Route::resource('broker', \App\Http\Controllers\Admin\BrokerController::class);
    Route::resource('email-sms-template', \App\Http\Controllers\Admin\EmailAndSmsTemplateController::class);
    Route::resource('policy-wording', \App\Http\Controllers\Admin\PolicyWordingController::class);
    Route::resource('master-product', \App\Http\Controllers\Admin\MasterPolicyController::class);
    Route::resource('company', \App\Http\Controllers\Admin\CompanyController::class);
    //Route::resource('mmv-data', \App\Http\Controllers\Admin\MmvDataController::class); // DO NOT ENABLE 
    Route::get('frontend-constant', [\App\Http\Controllers\Admin\FrontendConstantController::class,'index'])->name('frontend_index');
    Route::post('frontend-save', [\App\Http\Controllers\Admin\FrontendConstantController::class,'store'])->name('frontend_store'); 
    Route::post('frontend-update', [\App\Http\Controllers\Admin\FrontendConstantController::class,'update'])->name('frontend_update');
    Route::delete('frontend-delete', [\App\Http\Controllers\Admin\FrontendConstantController::class,'destroy'])->name('frontend_delete'); 
    Route::post('frontend-check', [\App\Http\Controllers\Admin\FrontendConstantController::class,'check'])->name('frontend_check'); 
    Route::group([
        'prefix' => 'ic-config',
        'as' => 'ic-config.',
    ], function () {
        Route::resource('commision-configurator', CommisionConfiguratorController::class);
        Route::resource('credential', \App\Http\Controllers\Admin\IcConfiguratorController::class);
        Route::get('product_config', [\App\Http\Controllers\Admin\IcConfiguratorController::class, 'fetchingProduct'])->name('fetchingProduct');
        Route::get('miscellaneous', [\App\Http\Controllers\Admin\IcConfiguratorController::class, 'miscellaneous'])->name('miscellaneous');
        Route::get('enable_cover', [\App\Http\Controllers\Admin\IcConfiguratorController::class, 'enable_cover'])->name('enable_cover');
        Route::post('cover_store', [\App\Http\Controllers\Admin\IcConfiguratorController::class, 'cover_store'])->name('cover_store');
        Route::post('update-product', [App\Http\Controllers\Admin\IcConfiguratorController::class, 'productUpdate']);
        Route::get('download-excel', [\App\Http\Controllers\Admin\IcConfiguratorController::class, 'downloadExcel'])->name('download-excel');
        Route::post('add', [App\Http\Controllers\Admin\IcConfiguratorController::class, 'storeOrUpdate']);
    });
    // Route::get('mmv-data-excel', [\App\Http\Controllers\Admin\MmvDataController::class , 'downloadExcel'])->name('mmv-data-excel');//do not enable
    Route::resource('pos-data', \App\Http\Controllers\Admin\PosDataController::class);
    Route::get('admin/pos_agents', [\App\Http\Controllers\Admin\PosDataController::class, 'agentList'])->name('pos-list');
    Route::resource('usp', \App\Http\Controllers\Admin\UspController::class);
    Route::get('usp-sample', [\App\Http\Controllers\Admin\UspController::class, 'uspSample'])->name('usp-sample');
    Route::resource('user', \App\Http\Controllers\Admin\UserController::class);
    Route::resource('role', \App\Http\Controllers\Admin\RoleController::class);
    Route::resource('password-policy', \App\Http\Controllers\Admin\PasswordPolicyController::class);
    Route::resource('report', \App\Http\Controllers\Admin\ReportController::class);
    Route::get('embedded-scrub', [\App\Http\Controllers\Admin\EmbeddedScrubController::class, 'index'])->name('embedded-scrub');
    Route::get('embedded-scrub-excel', [\App\Http\Controllers\Admin\EmbeddedScrubController::class, 'getEmbeddedScrubExcel'])->name('embedded-scrub-excel');
    Route::resource('rc-report', \App\Http\Controllers\Admin\RcReportController::class);
    Route::get('RcReportDownload/{uid}', [App\Http\Controllers\Admin\RcReportController::class, 'validateFile'])->name('RcReportDownload');
    Route::get('rc-report-download/{id}', [\App\Http\Controllers\Admin\RcReportController::class, 'download'])->name('rc-report-download');
    // Route::get('rc-report-proposal-validation', [\App\Http\Controllers\Admin\RcReportController::class, 'proposalValidation'])->name('rc-report.proposal-validation');
    Route::resource('rto-prefered', \App\Http\Controllers\Admin\RtoPreferredController::class);
    Route::resource('log', \App\Http\Controllers\Admin\LogController::class);
    Route::get('stage-count', [\App\Http\Controllers\Admin\StageCountController::class, 'view'])->name('stage-count');
    Route::resource('kafka-logs', \App\Http\Controllers\Admin\KafkaLogsController::class);
    Route::get('kafka-sync', [\App\Http\Controllers\Admin\KafkaLogsController::class, 'syncData'])->name('kafka-sync-data');
    Route::resource('trace-journey-id', \App\Http\Controllers\Admin\TraceJourneyIdController::class);
    Route::match(['get', 'post'], 'encrypt-decrypt', [\App\Http\Controllers\Admin\SecurityController::class, 'index'])->name('encrypt-decrypt');
    Route::get('ola-whatsapp-log', [\App\Http\Controllers\Admin\LogController::class, 'olaWhatsappLog'])->name('log.ola-whatsapp-log');
    Route::resource('payment-log', \App\Http\Controllers\Admin\PaymentResponseController::class);
    Route::resource('rto-master', \App\Http\Controllers\Admin\MasterRtoController::class);
    Route::resource('master-occuption', \App\Http\Controllers\Admin\MasterOccupationController::class);
    Route::resource('master-occupation-name', \App\Http\Controllers\Admin\MasterOccupationNameController::class);
    Route::resource('nominee-relation-ship', \App\Http\Controllers\Admin\NomineeRelationshipController::class);
    Route::resource('gender', \App\Http\Controllers\Admin\GenderController::class);
    Route::resource('financier-agreement-type', \App\Http\Controllers\Admin\FinancierAgreementTypeController::class);
    Route::resource('push-api', \App\Http\Controllers\Admin\PushApiController::class);
    Route::resource('icici-master', \App\Http\Controllers\Admin\IciciMasterDownloadController::class);
    Route::resource('bajaj-master', \App\Http\Controllers\Admin\BajajMasterController::class);
    Route::post('getBajajFile', [\App\Http\Controllers\Admin\BajajMasterController::class,'getBajajFile']);
    Route::resource('nominee-master', \App\Http\Controllers\Admin\NomineeController::class);
    // Route::resource('error-list-master', \App\Http\Controllers\Admin\ErrorListController::class);
    Route::resource('gender-master', \App\Http\Controllers\Admin\GenderNewController::class);
    Route::resource('finance-agreement-master', \App\Http\Controllers\Admin\FinanceAgreementNewController::class);
    Route::post('getfile', [\App\Http\Controllers\Admin\IciciMasterDownloadController::class, 'geticmaster'])->name('geticmaster');
    //Route::resource('addon-config', \App\Http\Controllers\Admin\AddonConfigurationController::class);
    Route::get('log-ongrid-fastlane', [\App\Http\Controllers\Admin\LogController::class, "ongridFastlaneLog"])->name('log.ongrid-fastlane');
    Route::get('log-third-paty-payment', [\App\Http\Controllers\Admin\LogController::class, "thirdPartyPaymentLog"])->name('log.third-paty-payment');
    Route::resource('manufacturer', \App\Http\Controllers\Admin\ManufactureController::class);
    Route::get('discount-domain/sample-file', [\App\Http\Controllers\Admin\DiscountDomainController::class, 'sampleFile'])->name('discount-domain.sample-file');
    Route::resource('discount-domain', \App\Http\Controllers\Admin\DiscountDomainController::class);
    Route::resource('previous-insurer', \App\Http\Controllers\Admin\PreviousInsurerController::class);
    Route::get('config-proposal-validation', [\App\Http\Controllers\Admin\MconfiguratorController::class,"proposalShow"])->name('config-proposal-validation');
    Route::get('config-field', [\App\Http\Controllers\Admin\MconfiguratorController::class,"fieldShow"])->name('config-field');
    Route::get('config-onboarding', [\App\Http\Controllers\Admin\MconfiguratorController::class,"onboardingShow"])->name('config-onboarding');
    Route::get('config-onboarding-fetch', [\App\Http\Controllers\Admin\MconfiguratorController::class,"onboardingFetch"])->name('onboardingConfig-fetch');
    Route::post('config-onboarding-save/{broker}', [\App\Http\Controllers\Admin\MconfiguratorController::class,"onboardingStore"])->name('onboardingConfig-store');
    Route::post('config-onboarding-save-file-config', [\App\Http\Controllers\Admin\MconfiguratorController::class,"saveFileIcConfig"])->name('onboardingConfig.store.fileConfig');
    Route::get('config-otp', [\App\Http\Controllers\Admin\MconfiguratorController::class,"otpShow"])->name('config-otp');
    Route::get('datapush-logs', [DataPushResReqLogController::class,"index"])->name('datapush_logs');
    Route::get('datapush-logs-view/{id?}', [DataPushResReqLogController::class,"datapushView"])->name('datapush_log_show');
    Route::get('datapush-logs-download/{type}/{id?}', [DataPushResReqLogController::class,"downloadDreqreslog"])->name('datapush_log_download');
    Route::get('renewal-upload-excel', [RenewalUploadExcelController::class,"index"])->name('renewal_upload_excel');
    Route::get('renewal-data-migration-logs', [RenewalUploadExcelController::class,"viewLogs"])->name('renewal_upload_migration_logs');
    Route::post('renewal-excel-uploaded', [RenewalUploadExcelController::class,"uploadRenewalExcel"])->name('renewal_upload_excel_post');
    Route::post('renewal-excel-validation-error', [RenewalUploadExcelController::class,"renewalErrorExcelExport"])->name('renewal_excel_validation_error');
    Route::match(['get', 'post'],'sql-runner', [SqlRunnerController::class,"index"])->name('sql_runner');
    Route::match(['get', 'post'],'make_selector', [MmvBlockerController::class,"index"])->name('make_selector');
    Route::match(['get', 'post'],'submit-form', [MmvBlockerController::class,"submitForm"])->name('submit-form');
    Route::post('delete-alias', [TemplateMasterController::class,"deleteAlias"])->name('delete_alias');
    Route::resource('template', TemplateMasterController::class);
    Route::resource('communication-configuration', CommunicationConfigurationController::class);
    Route::match(['get', 'post'],'hide_pyi', [HidePyiController::class,"index"])->name('hide_pyi');
    Route::match(['get', 'post'],'hide-pyi-store', [HidePyiController::class, 'store'])->name('hide_pyi.store');

    Route::get('show-html/{email_sms_template}', function (\App\Models\EmailSmsTemplate $EmailSmsTemplate) {
        return $EmailSmsTemplate->body;
    })->name('show-email-html');
    Route::resource('ic-error-handling', \App\Http\Controllers\Admin\IcErrorHandllingController::class);
    Route::resource('third_party_api_request_responses', \App\Http\Controllers\Admin\ThirdPartyApiRequestResponsesController::class);
    Route::resource('third_party_settings', \App\Http\Controllers\Admin\ThirdPartySettingsController::class);
    Route::resource('ckyc-logs', \App\Http\Controllers\Admin\CkycLogController::class)->only(['index']);
    Route::get('ckyc-logs/{id}/{table_name}', [\App\Http\Controllers\Admin\CkycLogController::class, 'show']);
    Route::resource('ckyc_not_a_failure_cases',\App\Http\Controllers\Admin\Ckyc\CkycNotAFailureCaseController::class);
    Route::resource('ckyc_verification_types',\App\Http\Controllers\Admin\Ckyc\CkycVerificationTypesController::class);
    Route::resource('ckyc-wrapper-logs', \App\Http\Controllers\Admin\Ckyc\CkycWrapperLogController::class)->only(['index', 'show']);
    Route::get('ckyc-wrapper-logs-download/{id}', [\App\Http\Controllers\Admin\Ckyc\CkycWrapperLogController::class, 'getLogs'])->name('ckyc-wrapper-logs-download');
    Route::resource('vahan-service-logs', \App\Http\Controllers\Admin\VahanServiceLogsController::class);
    Route::resource('cashless_garage', \App\Http\Controllers\Admin\CashlessGarageController::class);
    Route::get('db', function () {
        if (request()->key == '1') {
            return view('dddd');
        }
        return abort(404);
    });


    Route::group([
        'prefix' => 'mdm',
        'as' => 'mdm.'
    ], function () {
        Route::get('fetch-all-masters', [App\Http\Controllers\MasterDataManagementController::class, 'index'])->name('fetch.all.masters');
        Route::get('master-sync-logs', [App\Http\Controllers\MasterDataManagementController::class, 'getMdmLogs'])->name('sync.logs');
    });

    Route::group([
        'prefix' => 'pos-config',
        'as' => 'pos-config.'
    ], function () {
        Route::match(['GET', 'POST'], '/', [App\Http\Controllers\Admin\PosConfigController::class, 'index'])->name('home');
        Route::delete('/', [App\Http\Controllers\Admin\PosConfigController::class, 'destroy'])->name('destroy');
    });
    
    Route::group([
        'prefix' => 'ic-configuration',
        'as' => 'ic-configuration.'
    ], function () {
        
        Route::group([
            'prefix' => 'formulas',
            'as' => 'formula.'
        ], function () {
            Route::match(['GET', 'DELETE'], '/', [App\Http\Controllers\IcConfig\IcConfigurationController::class, 'listFormula'])->name('list-formula');
            Route::match(['GET', 'POST'], 'edit/{id}', [App\Http\Controllers\IcConfig\IcConfigurationController::class, 'editFormula'])->name('edit-formula');
            Route::get('view/{id}', [App\Http\Controllers\IcConfig\IcConfigurationController::class, 'viewFormula'])->name('view-formula');
            Route::match(['GET', 'POST'], 'create', [App\Http\Controllers\IcConfig\IcConfigurationController::class, 'createFormula'])->name('create-formula');
            Route::post('check-formula', [App\Http\Controllers\IcConfig\IcConfigurationController::class, 'checkFormula'])->name('check-formula');
        });

        Route::group([
            'prefix' => 'label-attributes',
        ],
            function () {
                Route::match(['GET', 'POST'], '/', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'labelAttributes'])->name('label-attributes');
                Route::get('view/{id}', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'viewLabel'])->name('view-label');
                Route::get('map-attributes/{id}', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'mapAttributes'])->name('map-attributes');
            }
        );
        Route::post('save-label', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'saveLabelData'])->name('save-label');
        Route::post('save-attributes', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'saveAttributes'])->name('save-attributes');
        Route::post('edit-label', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'editLabel'])->name('edit-label');
        Route::delete('delete-label', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'deleteLabel'])->name('delete-label');
        Route::delete('delete-attribute', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'deleteAttribute'])->name('delete-attribute');
        Route::get('get-attribute', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'getAttribute'])->name('get-attribute');
        Route::match(['GET', 'POST'], 'edit-attribute', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'editAttribute'])->name('edit-attribute');
        Route::get('get-edit-attribute', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'getEditAttribute'])->name('get-edit-attribute');

        Route::group([
            'prefix' => 'buckets',
            'as' => 'buckets.'
        ], function () {
            Route::match(['get', 'delete'], '/', [App\Http\Controllers\IcConfig\BucketListController::class, 'index'])->name('list');
            Route::match(['get', 'post'], 'create', [App\Http\Controllers\IcConfig\BucketListController::class, 'create'])->name('create');
            Route::get('view/{id}', [App\Http\Controllers\IcConfig\BucketListController::class, 'view'])->name('view');
            Route::match(['get', 'post'], 'edit/{id}', [App\Http\Controllers\IcConfig\BucketListController::class, 'edit'])->name('edit');
        });

      

        Route::group([
            'prefix' => 'version',
            'as' => 'version.'
        ], function () {
            Route::get('ic-version-configurator', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'icVersionConfigurator'])->name('ic-version-configurator');
            Route::get('save-version-data', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'saveVersionData'])->name('save-version-data');
            Route::get('update-version-data', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'updateVersionData'])->name('update-version-data');
            Route::delete('delete-version-data', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'deleteVerionData'])->name('delete-version-data');
            Route::get('add-version-data', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'addVersionData'])->name('add-version-data');
        });

        Route::group([
            'prefix' => 'placeholder',
            'as' => 'placeholder.'
        ], function () {
            Route::get('ic-placeholder', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'icPlaceholder'])->name('ic-placeholder');
            Route::get('save-placeholder', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'savePlaceholder'])->name('save-placeholder');
            Route::get('edit-placeholder', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'editPlaceholder'])->name('edit-placeholder');
            Route::delete('delete-placeholder', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'deletePlaceholder'])->name('delete-placeholder');
            Route::get('show-placeholder', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'showPlaceholder'])->name('show-placeholder');
        });

    });
    
    Route::group([
        'prefix' => 'config-onboarding',
        'as' => 'config-onboarding.'
    ], function () {
        Route::post('broker-logo', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class, "brokerLogo"])->name('broker-logo');
        Route::post('save-title-data', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class, "saveTitleData"])->name('save-title-data');
        Route::post('broker-url-redirection', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class, "brokerUrlRedirection"])->name('broker-url-redirection');
        Route::post('broker-scripts', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class, "brokerScripts"])->name('broker-scripts');
        Route::post('journey-configurator', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class, "journeyConfigurator"])->name('journey-configurator');
    });

    Route::group([
        'prefix' => 'ic-config',
        'as' => 'ic-config.',
    ], function () {
        Route::get('/listk', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'fetching'])->name('listk');
        Route::get('/ProductList', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'ProductList'])->name('ProductList');
        Route::get('/fetchingPremium', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'fetchingPremium'])->name('fetchingPremium');
        Route::get('/fetchingProduct', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'fetchingProduct'])->name('fetchingProduct');
        Route::get('/getProposalFields', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'getProposalFields'])->name('getProposalFields');
        Route::post('addProposalfield', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'addProposalfield']);
    });
    
    # For Testing Purpose.
    Route::get('/bcl-crm-data', function () {
        \App\Jobs\BajajCrmDataPushJob::dispatchSync();
    });
    Route::get('run-seeder/{class}', function ($class) {
        \Illuminate\Support\Facades\Artisan::call("db:seed", array('--class' => $class));
    });
    Route::get('/modify-user-id/{limit?}', function (Request $request, $limit = 50) {
        \App\Jobs\modifyUserIdsJob::dispatchSync($limit);
    });
    Route::get('server-error-logs', [\App\Http\Controllers\Admin\ServerErrorLogController::class, 'index'])->name('server-log');
    Route::resource('renewal-data-logs', \App\Http\Controllers\Admin\RenewalDataLogController::class);
    //Route::resource('previous-insurer-mapping', \App\Http\Controllers\Admin\PreviousInsurerMapppingController::class);
    Route::get('/export-users',[\App\Http\Controllers\Admin\PreviousInsurerMapppingController::class,'exportUsers']);
    Route::get('onepay-log', [\App\Http\Controllers\Admin\OnePay\TransactionLogController::class, 'index'])->name('onepay-log');
    //additional route
    Route::get('mongodb', [DashboardMongoLogsController::class, 'show'])->name('mongodb');
    Route::get('mongodb/final/{id}', [DashboardMongoLogsController::class, 'showdata']);
    ///additional view changes
    Route::resource('finsall-configuration', \App\Http\Controllers\Admin\Configuration\FinsallConfiguration::class);

    Route::group([
        'prefix' => 'ic-config',
        'as' => 'ic-config.',
    ], function () {
        Route::resource('commision-configurator', CommisionConfiguratorController::class);
        Route::resource('credential', \App\Http\Controllers\Lte\Admin\IcConfiguratorController::class);
        Route::get('product_config', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'fetchingProduct'])->name('fetchingProduct');
        Route::get('miscellaneous', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'miscellaneous'])->name('miscellaneous');
        Route::post('update-product', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'productUpdate']);
        Route::get('download-excel', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'downloadExcel'])->name('download-excel');
        Route::post('add', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'storeOrUpdate']);
    });

    Route::group([
        'prefix' => 'pos-config',
        'as' => 'pos-config.'
    ], function () {
        Route::match(['GET', 'POST'], '/', [App\Http\Controllers\Lte\Admin\PosConfigController::class, 'index'])->name('home');
        Route::delete('/', [App\Http\Controllers\Lte\Admin\PosConfigController::class, 'destroy'])->name('destroy');
    });

    Route::group([
        'prefix' => 'pg-config',
        'as' => 'pg-config.',
    ], function () {
        Route::match(['get', 'post'], '/', [\App\Http\Controllers\Admin\PaymentGatewayConfigurationController::class, 'index'])->name('home');
        Route::match(['get', 'post'], 'global-config', [\App\Http\Controllers\Admin\PaymentGatewayConfigurationController::class, 'globalConfig'])->name('global-config');
        Route::match(['get', 'post'], 'ic-wise-config', [\App\Http\Controllers\Admin\PaymentGatewayConfigurationController::class, 'icWiseConfig'])->name('ic-wise-config');
        
        Route::post('get-types', [\App\Http\Controllers\Admin\PaymentGatewayConfigurationController::class, 'getConfigType'])->name('get-type');
        Route::post('get-fields', [\App\Http\Controllers\Admin\PaymentGatewayConfigurationController::class, 'getGatewayFields'])->name('get-fields');
    });
});

//Route::get('/kafka', [App\Http\Controllers\KafkaController::class, 'test']);
Route::get('/kafkaManualDataPush/{enquiryId}', [\App\Http\Controllers\KafkaController::class, 'ManualDataPush']);
Route::get('/manualDashboardDataPush/{enquiryId}', [\App\Http\Controllers\KafkaController::class, 'ManualDataPush'])->name('manual-dashboard-push');
Route::get('/manualSyncPremiumDetails/{enquiryId}', [\App\Http\Controllers\PremiumDetailController::class, 'manualSyncPremiumDetails']);

Route::get('/cv/get-quote-pdf/{enquiry_id}', [\App\Http\Controllers\EnhanceJourneyController::class, 'getQuotePdf'])->name('cv.getQuotePdf');

Route::match(['get', 'post'], 'ckyc/response/motor/{company_alias}', [App\Http\Controllers\CkycController::class, 'ckycResponse'])->name('ckyc.response');

Route::get('/manualDataMigration', function () {
    if (config('constants.brokerConstant.ENABLE_MANUAL_RENEWAL_MIGRATION') == 'Y') {
        RenewalMigrationProcessSingle::dispatch();
        return response()->json([
            'status' => true,
            'message' => 'Manual renewal migration initiated.'
        ]);
    }
    return response()->json([
        'status' => false,
        'message' => 'This action is blocked.'
    ]);
})->name('manual-data-migrate');

Route::get('test-hdfc-payment-status-api', [DiscountConfigurationController::class, 'hdfcPaymentStatus']);

Route::get('clear_expired_ckyc_photos', function () {
    \Illuminate\Support\Facades\Artisan::call("clear:expired_ckyc_photos", []);
});

Route::get('downloadKafkaDetails' , [\App\Http\Controllers\Admin\downloadKafkaDetailsController::class , 'index']);
Route::post('downloadKafkaDetailsData' , [\App\Http\Controllers\Admin\downloadKafkaDetailsController::class , 'downloadKafka']);
Route::get('logs/download/{file_name}', [CommonController::class, 'logDownload']);
Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth' ,'track.user.activity']
], function () {
    Route::get('/', function () {
        return redirect('admin/dashboard');
    });
    Route::get('test', function(){
        return view('admin_lte.user.index');
    })->name('test');
    Route::resource('dashboard', \App\Http\Controllers\Lte\Admin\DashboardController::class);
    Route::resource('pa-insurance-masters', \App\Http\Controllers\Lte\Admin\pa_insurance_masters::class);
    Route::resource('vahan-upload', \App\Http\Controllers\Lte\Admin\VahanUpload::class);
    Route::get('download-vahan-excel', [\App\Http\Controllers\Lte\Admin\VahanUpload::class, 'downloadVahanExcel'])->name('download-vahan-excel');
    Route::resource('log_rotation', LogRotationController::class);
    Route::resource('user', \App\Http\Controllers\Lte\Admin\UserController::class);
    Route::resource('role', \App\Http\Controllers\Lte\Admin\RoleController::class);
    Route::get('user-trail', [\App\Http\Controllers\Lte\Admin\RoleController::class , 'userTrail' ]);
    Route::post('user-trails/filter', [\App\Http\Controllers\Lte\Admin\RoleController::class, 'filterUserTrails'])->name('user.trails.filter');
    Route::get('user-trails/export', [\App\Http\Controllers\Lte\Admin\RoleController::class, 'exportUserTrails'])->name('user.trails.export');
    Route::post('permission', [\App\Http\Controllers\Lte\Admin\RoleController::class , 'getPermission'])->name('permission');
    Route::post('save-permission', [\App\Http\Controllers\Lte\Admin\RoleController::class , 'savePermission'])->name('save-permission');
    Route::post('quick-link', [\App\Http\Controllers\Lte\Admin\RoleController::class , 'getQuickLink'])->name('quick-link');
    Route::post('save-quick-link', [\App\Http\Controllers\Lte\Admin\RoleController::class , 'saveQuickLink'])->name('save-quick-link');
    Route::resource('password-policy', \App\Http\Controllers\Lte\Admin\PasswordPolicyController::class);
    Route::resource('company', \App\Http\Controllers\Lte\Admin\CompanyController::class);
    Route::resource('ckyc_not_a_failure_cases',\App\Http\Controllers\Lte\Admin\Ckyc\CkycNotAFailureCaseController::class);
    Route::resource('ckyc_verification_types',\App\Http\Controllers\Lte\Admin\Ckyc\CkycVerificationTypesController::class);
    Route::resource('configuration', \App\Http\Controllers\Lte\Admin\ConfigurationController::class);
    Route::match(['get', 'post'], 'user-journey-activity', [\App\Http\Controllers\Lte\Admin\UserJourneyActivityController::class, 'index'])->name('user-journey-activity');
    Route::resource('pos-data', \App\Http\Controllers\Lte\Admin\PosDataController::class);
    Route::get('pos_agents', [\App\Http\Controllers\Lte\Admin\PosDataController::class, 'agentList'])->name('pos-list');
    Route::resource('master-product', \App\Http\Controllers\Lte\Admin\MasterPolicyController::class);
    Route::post('masterproduct-statusupdate', [\App\Http\Controllers\Lte\Admin\MasterPolicyController::class,'statusUpdate'])->name('masterproduct-statusupdate');
    //Route::resource('mmv-data', \App\Http\Controllers\Lte\Admin\MmvDataController::class);//DO NOT ENABLE
    Route::get('frontend-constant', [\App\Http\Controllers\Lte\Admin\FrontendConstantController::class,'index'])->name('frontend_index');
    Route::post('frontend-save', [\App\Http\Controllers\Lte\Admin\FrontendConstantController::class,'store'])->name('frontend_store'); 
    Route::post('frontend-update', [\App\Http\Controllers\Lte\Admin\FrontendConstantController::class,'update'])->name('frontend_update');
    Route::delete('frontend-delete', [\App\Http\Controllers\Lte\Admin\FrontendConstantController::class,'destroy'])->name('frontend_delete'); 
    Route::post('frontend-check', [\App\Http\Controllers\Lte\Admin\FrontendConstantController::class,'check'])->name('frontend_check'); 
    Route::resource('log', \App\Http\Controllers\Lte\Admin\LogController::class);Route::get('sync_mmv', function () {
        return view('admin_lte.mmv_sync.index');
    })->name('sync_mmv');
    //Route::get('mmv-data-excel', [\App\Http\Controllers\Lte\Admin\MmvDataController::class , 'downloadExcel'])->name('mmv-data-excel');//DO NOT ENABLE




    Route::post('vahan_configurator',[\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'VahanConfigurator'])->name('vahan_configurator');
    Route::resource('vahan_service', \App\Http\Controllers\Lte\Admin\VahanServiceController::class);
    Route::get('vahan_credentials_read/{id}', [\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'credCrudPage'])->name('vahan_credentials_read.crud');
    Route::match(['get', 'post'],'sql-runner', [\App\Http\Controllers\Lte\Admin\SqlRunnerController::class,"index"])->name('sql-runner');
    Route::delete('vahan_credentials/{id?}/{parameter?}', [\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'destroy'])->name('vahan_credentials.delete');
    Route::get('vahan-service-credentials', [\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'credData'])->name('vahan-service-credentials.index');
    Route::get('vahan-service-stage', [\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'stageIndex'])->name('vahan-service-stage.stageIndex');
    Route::get('vahan-service-stage-edit/{key}/{v_type}', [\App\Http\Controllers\Lte\Admin\VahanServiceController::class, 'stageEdit'])->name('vahan-service-stage-edit.stageEdit');
    Route::get('renewal-upload-excel', [\App\Http\Controllers\Lte\Admin\RenewalUploadExcelController::class,"index"])->name('renewal-upload-excel');
    Route::post('renewal-excel-uploaded', [\App\Http\Controllers\Lte\Admin\RenewalUploadExcelController::class,"uploadRenewalExcel"])->name('renewal_upload_excel_post');
    Route::resource('renewal-data-migration', \App\Http\Controllers\Lte\Admin\RenewalDataMigrationStatusController::class);
    Route::get('renewal-data-migration/download/{id}',[\App\Http\Controllers\Lte\Admin\RenewalDataMigrationStatusController::class, 'download'])->name('renewal-data-migration.download');
    Route::get('fetch-all-masters', [App\Http\Controllers\Lte\MasterDataManagementController::class, 'index'])->name('fetch.all.masters');
    Route::get('sync-logs', [App\Http\Controllers\Lte\MasterDataManagementController::class, 'getMdmLogs'])->name('sync.logs');
    Route::resource('report', \App\Http\Controllers\Lte\Admin\ReportController::class);
    Route::get('download-report/{uid}/{file}', [\App\Http\Controllers\Lte\Admin\ReportController::class, 'download'])->name('downloadReport');
    Route::resource('rc-report', \App\Http\Controllers\Lte\Admin\RcReportController::class);
    Route::get('rc-report-download/{id}', [\App\Http\Controllers\Lte\Admin\RcReportController::class, 'download'])->name('rc-report-download');
    Route::resource('user-activity-logs', \App\Http\Controllers\Lte\Admin\UserActivityLogsController::class);
    Route::resource('vahan-service-logs', \App\Http\Controllers\Lte\Admin\VahanServiceLogsController::class);
   
    Route::group([
        'prefix' => 'discount-configurations',
        'as' => 'discount-configurations.',
    ], function () {
        Route::match(['get', 'post'], 'config-setting', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'configSetting'])->name('config-setting');
        Route::match(['get', 'post'], 'global-config', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'globalConfig'])->name('global-config');
        Route::match(['get', 'post'], 'vehicle-config', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'vehicleConfig'])->name('vehicle-config');
        Route::match(['get', 'post'], 'ic-config', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'icConfig'])->name('ic-config');
        Route::match(['get', 'post'], 'active-config', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'activeConfig'])->name('active-config');
        Route::post('validate-ic', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'validateIcs'])->name('validate-ics');

        Route::get('activity-logs', [\App\Http\Controllers\Lte\Admin\DiscountConfigurationController::class, 'activityLogs'])->name('activity-logs');
    });

    Route::group([
        'prefix' => 'ic-configuration',
        'as' => 'ic-configuration.',
    ], function () {
        Route::get('view_attribute', [AttributeController::class, 'viewAttribute'])->name('view_attribute');
        Route::resource('premium-calculation-configurator', ConfiguredIcController::class);
        Route::post('clone/premium-calculation-configurator', [ConfiguredIcController::class, 'cloneIC'])->name('cloneIC');
        Route::get('premium-calculation-configurator/export/{id}', [ConfiguredIcController::class, 'export'])->name('export-config');
        Route::post('import/premium-calculation-configurator', [ConfiguredIcController::class, 'import'])->name('import-config');

    });

    Route::resource('master_product_type', MasterProductTypeController::class);

    Route::group([
        'prefix' => 'pg-config',
        'as' => 'pg-config.',
    ], function () {
        Route::match(['get', 'post'], '/', [\App\Http\Controllers\Lte\Admin\PaymentGatewayConfigurationController::class, 'index'])->name('home');
        Route::match(['get', 'post'], 'global-config', [\App\Http\Controllers\Lte\Admin\PaymentGatewayConfigurationController::class, 'globalConfig'])->name('global-config');
        Route::match(['get', 'post'], 'ic-wise-config', [\App\Http\Controllers\Lte\Admin\PaymentGatewayConfigurationController::class, 'icWiseConfig'])->name('ic-wise-config');
        
        Route::post('get-types', [\App\Http\Controllers\Lte\Admin\PaymentGatewayConfigurationController::class, 'getConfigType'])->name('get-type');
        Route::post('get-fields', [\App\Http\Controllers\Lte\Admin\PaymentGatewayConfigurationController::class, 'getGatewayFields'])->name('get-fields');
    });


    Route::get('datapush-logs', [\App\Http\Controllers\Lte\Admin\DataPushResReqLogController::class,"index"])->name('datapush-logs');
    Route::get('datapush-logs-view/{id?}', [\App\Http\Controllers\Lte\Admin\DataPushResReqLogController::class,"datapushView"])->name('datapush_log_show');
    Route::get('datapush-logs-download/{type}/{id?}', [\App\Http\Controllers\Lte\Admin\DataPushResReqLogController::class,"downloadDreqreslog"])->name('datapush_log_download');
    Route::get('onepay-log', [\App\Http\Controllers\Lte\Admin\OnePay\TransactionLogController::class, 'index'])->name('onepay-log');
    Route::resource('third_party_api_request_responses', \App\Http\Controllers\Lte\Admin\ThirdPartyApiRequestResponsesController::class);
    Route::resource('trace-journey-id', \App\Http\Controllers\Lte\Admin\TraceJourneyIdController::class);
    Route::get('common-config', [\App\Http\Controllers\Lte\Admin\CommonConfigurationsController::class, 'index'])->name('common-config');
    Route::post('common-config-save', [\App\Http\Controllers\Lte\Admin\CommonConfigurationsController::class, 'save'])->name('common-config-save');
    Route::get('config-proposal-validation', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"proposalShow"])->name('config-proposal-validation');
    Route::get('config-field', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"fieldShow"])->name('config-field');
    Route::get('config-onboarding', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"onboardingShow"])->name('config-onboarding');
    Route::get('config-onboarding-fetch', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"onboardingFetch"])->name('onboardingConfig-fetch');
    Route::post('config-onboarding-save/{broker}', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"onboardingStore"])->name('onboardingConfig-store');
    Route::post('config-onboarding-save-file-config', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"saveFileIcConfig"])->name('onboardingConfig.store.fileConfig');
    Route::get('config-otp', [\App\Http\Controllers\Lte\Admin\MconfiguratorController::class,"otpShow"])->name('config-otp');
    Route::resource('manufacturer', \App\Http\Controllers\Lte\Admin\ManufactureController::class);
    Route::resource('usp', \App\Http\Controllers\Lte\Admin\UspController::class);
    Route::get('usp-sample', [\App\Http\Controllers\Lte\Admin\UspController::class, 'uspSample'])->name('usp-sample');
    Route::resource('ic-master', \App\Http\Controllers\Lte\Admin\IciciMasterDownloadController::class);
    Route::post('getfile', [\App\Http\Controllers\Lte\Admin\IciciMasterDownloadController::class, 'geticmaster'])->name('geticmaster');
    Route::resource('bajaj-master', \App\Http\Controllers\Lte\Admin\BajajMasterController::class);
    Route::post('getBajajFile', [\App\Http\Controllers\Lte\Admin\BajajMasterController::class,'getBajajFile']);
    Route::post('getIffcoMasterFile', [\App\Http\Controllers\Lte\Admin\IffcoMasterController::class,'getIffcoMasterFile']);
    Route::resource('previous-insurer', \App\Http\Controllers\Lte\Admin\PreviousInsurerController::class);
    Route::resource('broker', \App\Http\Controllers\Lte\Admin\BrokerController::class);
    Route::resource('ic-return-url', \App\Http\Controllers\Lte\Admin\IcReturnUrlController::class);
    Route::resource('rto-prefered', \App\Http\Controllers\Lte\Admin\RtoPreferredController::class);
    Route::resource('payment-log', \App\Http\Controllers\Lte\Admin\PaymentResponseController::class);
    Route::resource('rto-master', \App\Http\Controllers\Lte\Admin\MasterRtoController::class);
    Route::resource('master-occupation', \App\Http\Controllers\Lte\Admin\MasterOccupationController::class);
    Route::resource('master-occupation-name', \App\Http\Controllers\Lte\Admin\MasterOccupationNameController::class);
    Route::resource('third_party_settings', \App\Http\Controllers\Lte\Admin\ThirdPartySettingsController::class);
    Route::resource('vahan-journey-config', \App\Http\Controllers\Lte\Admin\VahanJourneyCondifgController::class);
    Route::resource('queue-management', \App\Http\Controllers\Lte\Admin\queueManagementController::class);
    Route::resource('template', \App\Http\Controllers\Lte\Admin\TemplateMasterController::class);
    Route::resource('log_configurator', \App\Http\Controllers\Lte\Admin\LogConfiguratorMasterController::class);
    Route::resource('cashless_garage', \App\Http\Controllers\Lte\Admin\CashlessGarageController::class);
    Route::resource('policy-wording', \App\Http\Controllers\Lte\Admin\PolicyWordingController::class);
    Route::resource('finance-agreement-master', \App\Http\Controllers\Lte\Admin\FinanceAgreementNewController::class);
    Route::resource('nominee-master', \App\Http\Controllers\Lte\Admin\NomineeController::class);
    Route::resource('gender-master', \App\Http\Controllers\Lte\Admin\GenderNewController::class);
    Route::resource('ic-error-handling', \App\Http\Controllers\Lte\Admin\IcErrorHandllingController::class);
    Route::match(['get', 'post'], 'encrypt-decrypt', [\App\Http\Controllers\Lte\Admin\SecurityController::class, 'index'])->name('encrypt-decrypt');
    Route::match(['get', 'post'], 'kotak-encrypt-decrypt', [\App\Http\Controllers\Lte\Admin\KotakDecryptionController::class, 'index'])->name('kotak-encrypt-decrypt');
    Route::get('server-error-logs', [\App\Http\Controllers\Lte\Admin\ServerErrorLogController::class, 'index'])->name('server-log');
    Route::resource('renewal-data-logs', \App\Http\Controllers\Lte\Admin\RenewalDataLogController::class);
    Route::resource('ckyc-logs', \App\Http\Controllers\Lte\Admin\CkycLogController::class)->only(['index']);
    Route::resource('ckyc-redirection-logs', \App\Http\Controllers\Lte\Admin\CkycRedirectionLogController::class)->only(['index']);
    Route::get('ckyc-logs/{id}/{table_name}', [\App\Http\Controllers\Lte\Admin\CkycLogController::class, 'show']);
    Route::get('ckyc-redirection-logs/{id}/{table_name}', [\App\Http\Controllers\Lte\Admin\CkycRedirectionLogController::class, 'show']);
    Route::resource('ckyc-wrapper-logs', \App\Http\Controllers\Lte\Admin\Ckyc\CkycWrapperLogController::class)->only(['index', 'show']);
    Route::get('stage-count', [\App\Http\Controllers\Lte\Admin\StageCountController::class, 'view'])->name('stage-count');
    Route::resource('kafka-logs', \App\Http\Controllers\Lte\Admin\KafkaLogsController::class);
    Route::get('third-paty-payment', [\App\Http\Controllers\Lte\Admin\LogController::class, "thirdPartyPaymentLog"])->name('third-paty-payment');
    Route::get('journey-data', [\App\Http\Controllers\Lte\Admin\DashboardController::class, 'getJourneyData'])->name('journey-data.index');
    Route::resource('push-api', \App\Http\Controllers\Lte\Admin\PushApiController::class);
    Route::resource('BrokerageLogs', \App\Http\Controllers\Lte\Admin\BrokerageLogsController::class);
    Route::get('mdm_master', [\App\Http\Controllers\Lte\Admin\MdmMasterController::class, 'index'])->name('mdm_master.index');
    Route::post('mdm_master', [\App\Http\Controllers\Lte\Admin\MdmMasterController::class, 'store'])->name('mdm_master.store');
    Route::post('mdm_master/update', [\App\Http\Controllers\Lte\Admin\MdmMasterController::class, 'update'])->name('mdm_master.update');
    Route::post('mdm_master/delete', [\App\Http\Controllers\Lte\Admin\MdmMasterController::class, 'destroy'])->name('mdm_master.destroy');
    Route::resource('insurer_logo_priority_list',\App\Http\Controllers\Lte\Admin\InsurerLogoPriorityListController::class);
    Route::get('reset/insurer_logo_priority_list',[\App\Http\Controllers\Lte\Admin\InsurerLogoPriorityListController::class,'reset']);
    
    Route::group([
        'prefix' => 'mongodb',
    ], function () {
        Route::get('/', [\App\Http\Controllers\Lte\Admin\DashboardMongoLogsController::class, 'show'])->name('mongodb');
        Route::get('final/{id}', [\App\Http\Controllers\Lte\Admin\DashboardMongoLogsController::class, 'showdata']);
    });

    Route::get('kafka-sync-data', [\App\Http\Controllers\Lte\Admin\KafkaLogsController::class, 'syncData'])->name('kafka-sync-data');

    Route::group([
        'prefix' => 'ic-config',
        'as' => 'ic-config.',
    ], function () {
        Route::resource('credential', \App\Http\Controllers\Lte\Admin\IcConfiguratorController::class);
        Route::get('product_config', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'fetchingProduct'])->name('fetchingProduct');
        Route::get('miscellaneous', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'miscellaneous'])->name('miscellaneous');
        Route::post('update-product', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'productUpdate']);
        Route::get('download-excel', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'downloadExcel'])->name('download-excel');
        Route::post('add', [\App\Http\Controllers\Lte\Admin\IcConfiguratorController::class, 'storeOrUpdate']);
    });

    Route::resource('menu', \App\Http\Controllers\Admin\MenuMasterController::class);
    Route::get('menu/edit/{id}', [\App\Http\Controllers\Admin\MenuMasterController::class, 'edit']);
    Route::post('menu/update', [\App\Http\Controllers\Admin\MenuMasterController::class, 'update']);

    Route::resource('commission-api-logs', \App\Http\Controllers\Lte\Admin\CommissionApiLogController::class);
    
    Route::get('authorization_requests', [\App\Http\Controllers\Lte\Admin\AuthorizationRequestController::class, 'index'])->name('authorization_request');
    Route::post('/mark-as-read', [\App\Http\Controllers\Lte\Admin\AuthorizationRequestController::class, 'markAsRead'])->name('markAsRead');
    Route::post('authorization_requests/approve_request', [\App\Http\Controllers\Lte\Admin\AuthorizationRequestController::class, 'approve_request']);
    Route::get('approval-status', [\App\Http\Controllers\Lte\Admin\AuthorizationRequestController::class, 'approvalStatus']);
    Route::get('update-profile/{id}', [\App\Http\Controllers\Lte\Admin\UserController::class, 'updateProfile'])->name('update-profile');
    Route::post('save-profile/{id}', [\App\Http\Controllers\Lte\Admin\UserController::class, 'saveProfile'])->name('save-profile');
   // Route::resource('icici-master', \App\Http\Controllers\Lte\Admin\IciciMasterDownloadController::class);
    Route::any('icici-master', function () { return redirect('admin/ic-master'); });
    Route::resource('inspection', App\Http\Controllers\Lte\Admin\InspectionTypeController::class);
    Route::get('inspection/edit/{id}', [App\Http\Controllers\Lte\Admin\InspectionTypeController::class, 'edit']);
    Route::post('inspection/update', [App\Http\Controllers\Lte\Admin\InspectionTypeController::class, 'update']);
    Route::resource('pos-service-logs', App\Http\Controllers\Lte\Admin\PosServiceLogs::class);
    Route::resource('manufacturer-priority',App\Http\Controllers\Lte\Admin\ManufacturerPriorityController::class);
    Route::get('manufacturer-priorityfetchinsurers', [App\Http\Controllers\Lte\Admin\ManufacturerPriorityController::class, 'fetchInsurers'])->name('fetchInsurers');

    Route::group([
        'prefix' => 'update-registration-date',
        'as' => 'update-registration-date.',
    ], function () {
        Route::resource('/', \App\Http\Controllers\Lte\Admin\UpdateRegistrationDate::class);
        Route::post('save-registration-date', [\App\Http\Controllers\Lte\Admin\UpdateRegistrationDate::class, 'store'])->name('save-registration-date');
    });   
    //--x--Boot Config Route---x //
    Route::get('boot-config', [\App\Http\Controllers\Lte\Admin\BootConfigController::class, 'show'])->name('boot-config');
    Route::post('update', [\App\Http\Controllers\Lte\Admin\BootConfigController::class, 'update'])->name('env.update');
});
//Log download
Route::get('logs/download/{file_name}', [CommonController::class, 'logDownload']);

Route::get('policy-download/{enquiryId}', [\App\Http\Controllers\PolicyDownloadController::class, 'download'])->name('policy-download');
Route::get('policy-download-url/{url}', [\App\Http\Controllers\PolicyDownloadController::class, 'downloadWithUrl'])->name('policy-download-url');