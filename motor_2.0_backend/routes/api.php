<?php

use App\Http\Controllers\Lte\Admin\CommisionConfiguratorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MmvController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\CommonController;
use App\Http\Controllers\IciciMmvController;
use App\Http\Controllers\ProposalValidation;
use App\Http\Controllers\Mail\MailController;
use App\Http\Controllers\EnhanceJourneyController;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\ProposalReportController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\admin\MasterRtoController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\IcConfiguratorController;

use App\Http\Controllers\Admin\RenewalDataMigrationStatusController;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\TokenGenerationController;
use App\Http\Controllers\PosRegistrationController;
use App\Http\Controllers\magma\MagmaCarApiController;
use App\Http\Controllers\sbi\SbiApiRequestController;
use App\Http\Controllers\magma\MagmaBikeApiController;
use App\Http\Controllers\RenewBuyKafkaMessageController;
use App\Http\Controllers\Inspection\InspectionController;
use App\Http\Controllers\Payment\ServerToServerController;
use App\Http\Controllers\TmibaslNsdlApiController;
use App\Http\Controllers\GetKafkaData;
use App\Http\Controllers\PospUtility\PospUtilityController;
use App\Http\Controllers\QuotationProcess\QuoteService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */


use App\Http\Controllers\Inspection\CarInspectionController;
use App\Http\Controllers\Inspection\BikeInspectionController;
use App\Http\Controllers\Inspection\Service\RelianceInspectionService;
use App\Http\Controllers\LSQ\ActivityController;
use App\Http\Controllers\LSQ\OpportunityController;

use App\Http\Controllers\Finsall\FinsallController;
use App\Http\Controllers\GenericController;
use App\Http\Controllers\InspectionTypeUtilityController;
use App\Http\Controllers\LogRotationController;
use App\Http\Controllers\RenewalReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('userProfiling', [\App\Http\Controllers\UserProfilingController::class, 'addData']);

Route::put('artisan-command', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    echo "<pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
});
// Route::post('truncateTable/{email}', [CommonController::class, 'truncateTable']);
Route::post('userLogin', [UserManagementController::class, 'userLogin']);
Route::post('login', [GenericController::class, 'login']);
Route::post('createLead', [UserManagementController::class, 'createLead']);
Route::post('getProductDetails', [CommonController::class, 'getProductDetails']);
Route::post('callUs', [MailController::class, 'callUs']);

//Middleware auth api
// Route::middleware(('auth:api'))->group(function () {
Route::post('addProposalfield', [ProposalValidation::class, 'addProposalfield']);
Route::post('enhanceJourney', [EnhanceJourneyController::class, 'enhanceJourney']);
Route::post('generateEmbeddedLink', [EnhanceJourneyController::class, 'generateEmbeddedLink']);
Route::match(['get', 'post'], 'inapp-journey-redirection', [EnhanceJourneyController::class, 'inappJourneyRedirection']);
Route::match(['get', 'post'], 'aceCrmLeadId', [EnhanceJourneyController::class, 'aceCrmLeadId']);
Route::post('AdrilaJourneyRedirection', [EnhanceJourneyController::class, 'AdrilaJourneyRedirection']);
Route::post('getVehicleCategory', [CommonController::class, 'getVehicleCategory']);
Route::post('getVehicleSubType', [CommonController::class, 'getVehicleSubType']);
Route::post('getManufacturer', [CommonController::class, 'getManufacturer']);
Route::post('getVehicleType', [CommonController::class, 'getVehicleType']);
Route::post('setMmvPriority', [CommonController::class, 'setMmvPriority']);
Route::post('updateVersionCount', [CommonController::class, 'updateVersionCount']);
Route::post('setRtoPriority', [CommonController::class, 'setRtoPriority']);
Route::post('getModel', [CommonController::class, 'getModel']);
Route::post('getFuelType', [CommonController::class, 'getFuelType']);
Route::post('getNcb', [CommonController::class, 'getNcb']);
Route::post('getOwnerTypes', [CommonController::class, 'getOwnerTypes']);
Route::post('getVehicleInfo', [CommonController::class, 'getVehicleInfo']);
Route::post('getRto', [CommonController::class, 'getRto']);
Route::post('getRelationshipMapping', [CommonController::class, 'getRelationshipMapping']);
Route::post('checkPincode', [CommonController::class, 'checkPincode']);
Route::post('getPreviousInsurers', [CommonController::class, 'getPreviousInsurers']);
Route::post('getPreviousInsurerList', [CommonController::class, 'getPreviousInsurerList']);
Route::post('getAddonList', [CommonController::class, 'getAddonList']);
Route::post('getModelVersion', [CommonController::class, 'getModelVersion']);
Route::post('setLeadStage', [CommonController::class, 'setLeadStage']);
Route::post('getGenderType', [CommonController::class, 'getGenderType']);
Route::post('getOccupationType', [CommonController::class, 'getOccupationType']);
Route::post('getMaritalStatusType', [CommonController::class, 'getMaritalStatusType']);
Route::post('saveQuoteRequestData', [CommonController::class, 'saveQuoteRequestData']);
Route::post('saveQuoteData', [CommonController::class, 'saveQuoteData']);
Route::post('saveAddonData', [CommonController::class, 'saveAddonData']);
Route::post('getUserRequestedData', [CommonController::class, 'getUserRequestedData']);
Route::post('updateQuoteRequestData', [CommonController::class, 'updateQuoteRequestData']);
Route::post('updateUserJourney', [CommonController::class, 'updateUserJourney']);
Route::post('premiumCalculation/{company_alias}', [App\Http\Controllers\Quotes\Cv\CvQuoteController::class, 'premiumCalculation']);
Route::post('createEnquiryId', [CommonController::class, 'createEnquiryId']);
Route::post('save', [App\Http\Controllers\Proposal\ProposalController::class, 'save']);
Route::post('submit', [App\Http\Controllers\Proposal\ProposalController::class, 'submit']);
Route::post('createEnquiryId', [CommonController::class, 'createEnquiryId']);
Route::post('make-payment', [App\Http\Controllers\Payment\PaymentController::class, 'makePayment']);
// Route::match(['get', 'post'], 'cv/payment-confirm/{ic_name}', [App\Http\Controllers\Payment\PaymentController::class, 'confirm'])->name('cv.payment-confirm');
Route::match(['get', 'post'], 'previousInsurer', [CommonController::class, 'previousInsurers']);
Route::post('getVoluntaryDiscounts', [CommonController::class, 'getVoluntaryDiscounts']);
Route::match(['get', 'post'],'getOccupation', [CommonController::class, 'getOccupation']);
Route::match(['get', 'post'] ,'getNomineeRelationship', [CommonController::class, 'getNomineeRelationship']);
Route::match(['get', 'post'], 'getGender', [CommonController::class, 'getGender']);
Route::match(['get', 'post'],'getFinancerList', [CommonController::class, 'getFinancerList']);
Route::post('getFinancerBranch', [CommonController::class, 'getFinancerBranch']);
Route::match(['get', 'post'],'getFinancerAgreementType', [CommonController::class, 'getFinancerAgreementType']);
Route::match(['get', 'post'],'getPincode', [CommonController::class, 'getIcPincode']);
Route::match(['get', 'post'],'getPolicyDetails', [CommonController::class, 'getPolicyDetails']);
Route::match(['get', 'post'],'feedback', [CommonController::class, 'feedback']);
Route::post('inspectionConfirm', [InspectionController::class, 'inspectionConfirm']);
Route::post('getInspectionList', [InspectionController::class, 'getInspectionList']);
Route::post('getAppInspectionList', [InspectionController::class, 'getAppInspectionList']);
Route::post('rehit_pdf', [PDFController::class, 'rehitPdf']);
Route::post('tokenValidate', [CommonController::class, 'tokenValidate']);
Route::post('proposalReports', [ProposalReportController::class, 'proposalReports']);
Route::post('proposal-reports-dashboard', [ProposalReportController::class, 'proposalReportsDashboard']);
Route::post('oemproposalReports', [ProposalReportController::class, 'oemproposalReports']);
Route::post('proposalReportsCount', [ProposalReportController::class, 'proposalReportsCount']);
Route::post('proposalReportsByLeadId', [ProposalReportController::class, 'proposalReportsByLeadId']);
Route::post('renewalReports', [RenewalReportController::class, 'renewalReports']);
Route::post('sendEmail', [MailController::class, 'sendEmail']);
Route::post('sendOtp', [MailController::class, 'sendOtp']);
Route::post('whatsappNotification', [MailController::class, 'whatsappNotification']);
Route::get('whatsappNotificationNew', [MailController::class, 'whatsappNotificationNew']);
Route::post('getBreakinCompany', [CommonController::class, 'getBreakinCompany']);
Route::match(['get', 'post'],'masterCompanyLogos', [CommonController::class, 'masterCompanyLogos']);
Route::post('cvApplicableAddons', [CommonController::class, 'cvApplicableAddons']);
Route::post('policyPdfUpload', [CommonController::class, 'policyPdfUpload']);
Route::get('getAbiblMgMapping', [MmvController::class, 'getAbiblMgMapping']);
Route::post('getEnq', [CommonController::class, 'getEnq']);
Route::post('getProductSubType', [CommonController::class, 'getProductSubType']);
Route::match(['get', 'post'],'getWordingsPdf', [CommonController::class, 'getWordingsPdf']);
Route::match(['get', 'post'],'getVehicleCategories', [CommonController::class, 'getVehicleCategories']);
Route::match(['get', 'post'],'getVehicleUsageTypes', [CommonController::class, 'getVehicleUsageTypes']);
Route::match(['get', 'post'],'getOrganizationTypes', [CommonController::class, 'getOrganizationTypes']);
Route::match(['get', 'post'],'getIndustryTypes', [CommonController::class, 'getIndustryTypes']);

Route::post('updateJourneyUrl', [CommonController::class, 'updateJourneyUrl']);
Route::post('getUsp', [CommonController::class, 'getUsp']);
Route::post('generatePdf', [App\Http\Controllers\Payment\ReHitPdfController::class, 'generatePdf']);
Route::post('generatePdfAll', [App\Http\Controllers\Payment\ReHitPdfController::class, 'generatePdfAll']);
Route::post('ReconService', [App\Http\Controllers\Payment\ReconController::class, 'ReconService']);

//CAR ROUTE START
Route::post('car/premiumCalculation/{company_alias}', [App\Http\Controllers\Quotes\Car\CarQuoteController::class, 'premiumCalculation']);
Route::post('car/submit', [App\Http\Controllers\Proposal\CarProposalController::class, 'submit']);
Route::post('car/make-payment', [App\Http\Controllers\Payment\CarPaymentController::class, 'makePayment']);
Route::post('car/inspectionConfirm', [CarInspectionController::class, 'inspectionConfirm']);
Route::post('car/getInspectionList', [CarInspectionController::class, 'getInspectionList']);
//CAR ROUTe END

//BIKE ROUTE START
Route::post('bike/premiumCalculation/{company_alias}', [App\Http\Controllers\Quotes\Bike\BikeQuoteController::class, 'premiumCalculation']);
Route::post('bike/submit', [App\Http\Controllers\Proposal\BikeProposalController::class, 'submit']);
Route::post('bike/make-payment', [App\Http\Controllers\Payment\BikePaymentController::class, 'makePayment']);
//BIKE ROUTE START


Route::post('cashlessGarage', [App\Http\Controllers\CommonController::class, 'cashlessGarage']);
Route::match(['get', 'post'], 'premiumBreakupPdf', [App\Http\Controllers\PDFController::class, 'premiumBreakupPdf']);
Route::match(['get', 'post'],'premiumBreakupPdfemail', [App\Http\Controllers\PDFController::class, 'premiumBreakupPdfemail']);
//Route::get('policyComparePdf', [App\Http\Controllers\PDFController::class, 'policyComparePdf']);
Route::match(['get', 'post'], 'policyComparePdf', [App\Http\Controllers\PDFController::class, 'policyComparePdf']);
Route::post('proposalPagePdf', [App\Http\Controllers\PDFController::class, 'proposalPagePdf']);
Route::post('verifysmsotp', [MailController::class, 'verifySMSOtp']);

Route::match(['get', 'post'], 'premiumBreakupMail', [MailController::class, 'premiumBreakupMail']);
Route::match(['get', 'post'], 'comapareEmail', [MailController::class, 'comapareEmail']);
Route::match(['get', 'post'], 'themeConfig', [CommonController::class, 'themeConfig'])->name("api.themeConfig");
Route::post('car/GetIIBDetails', [App\Http\Controllers\Proposal\Services\Car\magmaSubmitProposal::class, 'GetIIBDetails']);

Route::get('updateLead/{inspectionNo}', [RelianceInspectionService::class, 'updateLead']);
Route::match(['get', 'post'], 'car/{company_alias}/updateBreakinStatus', [CarInspectionController::class, 'updateBreakinStatus']);
Route::match(['get', 'post'], 'bike/{company_alias}/updateBreakinStatus', [BikeInspectionController::class, 'updateBikeBreakinStatus']);

Route::match(['get', 'post'], 'getVehicleDetails', [CommonController::class, 'getVehicleDetails']);
Route::get('getAgents', [CommonController::class, 'getAgents']);

Route::post('whatsapphistory', [MailController::class, 'whatsapphistory']);
Route::match(['get', 'post'], 'whatsapp', [MailController::class, 'whatsappNotificationNew']);
// Route::match(['POST', 'GET'], 'whatsapp', function () {
//     \App\Models\WhatsappRequestResponse::create([
//         'ip' => request()->ip(),
//         'request_id' => request()->id,
//         'mobile_no' => request()->mobile,
//         'request' => request()->all(),
//     ]);
//     MailController::whatsappNotificationNew();
// });
Route::get('/sbi-prod-token', [SbiApiRequestController::class, 'tokenGenerationMotor']);
Route::get('/sbi-token', [SbiApiRequestController::class, 'tokenGeneration']);
Route::post('/sbi-full-quote', [SbiApiRequestController::class, 'fullQuote']);
Route::post('/sbi-gcv-full-quote', [SbiApiRequestController::class, 'gcvfullQuote']);
Route::post('createDuplicateJourney', [CommonController::class, 'createDuplicateJourney']);
Route::match(['get', 'post'],'getColor', [CommonController::class, 'getColor']);

Route::post('magma/bike/token', [MagmaBikeApiController::class, 'tokenGeneration']);
Route::post('magma/bike/premiumCalculation', [MagmaBikeApiController::class, 'premiumCalculation']);
Route::post('magma/bike/iibVerification', [MagmaBikeApiController::class, 'iibVerification']);
Route::post('magma/bike/proposalGeneration', [MagmaBikeApiController::class, 'proposalGeneration']);
Route::post('magma/bike/proposalStatus', [MagmaBikeApiController::class, 'proposalStatus']);
Route::post('magma/bike/pgRedirection', [MagmaBikeApiController::class, 'pgRedirection']);
Route::post('magma/bike/policyGeneration', [MagmaBikeApiController::class, 'policyGeneration']);

Route::post('magma/car/token', [MagmaCarApiController::class, 'tokenGeneration']);
Route::post('magma/car/premiumCalculation', [MagmaCarApiController::class, 'premiumCalculation']);
Route::post('magma/car/iibVerification', [MagmaCarApiController::class, 'iibVerification']);
Route::post('magma/car/proposalGeneration', [MagmaCarApiController::class, 'proposalGeneration']);
Route::post('magma/car/proposalStatus', [MagmaCarApiController::class, 'proposalStatus']);
Route::post('magma/car/pgRedirection', [MagmaCarApiController::class, 'pgRedirection']);
Route::post('magma/car/policyGeneration', [MagmaCarApiController::class, 'policyGeneration']);
Route::get('getexshowroom', [IciciMmvController::class, 'getIciciShowRoomPrice']);
Route::post('posregistration', [PosRegistrationController::class, 'posregistration']);
// Inspection Module API
Route::group([
    'prefix' => 'inspection-app',
], function () {
    Route::post('upload-video', [\App\Http\Controllers\Inspection\VideoUploadController::class, 'upload']);
});


Route::get('/get_state', [MasterRtoController::class, 'get_state']);
Route::get('/get_zone', [MasterRtoController::class, 'get_zone']);
Route::post('linkDelivery', [CommonController::class, 'linkDelivery']);
Route::match(['get', 'post'], 'frontendUrl', [CommonController::class, 'frontendUrl']);
Route::post('getDefaultCovers', [CommonController::class, 'getDefaultCovers']);
Route::get('GetIssuePolicyList', [CommonController::class, 'GetIssuePolicyList']);
Route::group([
    'prefix' => 'renewbuy',
], function () {
    Route::match(['get', 'post'], '{product_type}/GenerateLead', [EnhanceJourneyController::class, 'renewbuyGenerateLead']);
    Route::match(['get', 'post'], '{product_type}/GenerateLeadB2C', [EnhanceJourneyController::class, 'renewbuyGenerateLead']);
    Route::post("motor/kafkaMessages", [RenewBuyKafkaMessageController::class, 'getMessages']);
});
Route::match(['get', 'post'], 'renewal/{product_type}/GenerateLead', [RenewalController::class, 'renewalGenerateLead']);
Route::match(['get', 'post'], '{product_type}/GenerateLead', [RenewalController::class, 'GenerateLead']);
Route::get('GetReturnUrl', [CommonController::class, 'GetReturnUrl']);
Route::post('logout', [CommonController::class, 'logout']);

Route::post('dashboard/policies/email_pdf', [MailController::class, 'policyShareDashboard']);

Route::post('/create-activity', [ActivityController::class, 'create']);

Route::post('url-request', [CommonController::class, 'urlRequest']);

Route::group([
    'prefix' => 'abibl',
], function () {
    Route::post('quoteUrl', [\App\Http\Controllers\AbiblController::class, 'quoteUrl']);
    Route::get('wrapper', [\App\Http\Controllers\AbiblController::class, 'wrapper']);
    Route::post('fyntune-callback-status-update', [\App\Http\Controllers\AbiblController::class, 'blockList']);
});

Route::post('finsall/getEntityTypeAndNameById', [FinsallController::class, 'getEntityTypeAndNameById']);
Route::post('finsall/saveOrUpdateBankSelector', [FinsallController::class, 'saveOrUpdateBankSelector']);

Route::match(['get', 'post'], 'finsall/payment-confirm', [FinsallController::class, 'paymentConfirm']);

Route::post('getJourneyDetailsByRCNumber', [EnhanceJourneyController::class, 'getJourneyDetailsByRCNumber']);
Route::post('embeddedScrub', [EnhanceJourneyController::class, 'embeddedScrub']);
Route::post('getScrubData', [EnhanceJourneyController::class, 'getScrubData']);
Route::post('logs', [GenericController::class, 'getLogs']);
// Route::get('log/{type}/{id}', [GenericController::class, 'getLog'])->name('api.logs');

Route::get('proposalReportsForLargeData', [ProposalReportController::class, 'proposalReportsForLargeData']);
Route::get('getKafkaData', [GetKafkaData::class, 'getKafkaData']);
Route::get('modifySellerType', [GenericController::class, 'modifySellerType']);

Route::get('log-details', [GenericController::class, 'getLogsQuoteDetails']);
Route::get('log-count/{type}', [GenericController::class, 'quoteVisibilityCount'])->name('api.logs.count');
Route::post('renewal/count', [GenericController::class, 'renewalCountByDays']);
Route::post('renewal/ic-wise/count', [GenericController::class, 'renewalCountByIc']);
Route::post('log-details', [GenericController::class, 'getLogsQuote'])->name('api.logs.details');

Route::post('agentMobileValidator', [GenericController::class, 'agentMobileValidator']);
Route::post('agentEmailValidator', [GenericController::class, 'agentEmailValidator']);

Route::post('shorten_url', [MailController::class, 'shortUrlService']);

Route::match(['get', 'post'], 'ckyc-response/{ic_alias}', [App\Http\Controllers\CkycController::class, 'ckycResponseAPi'])->name('ckyc.responseApi')->middleware('secure.http.request');
Route::post('renewal-data-upload', [CommonController::class, 'renewalDataUpload'])->name('api.renewal-data-upload');

Route::post('data-upload-v2', [\App\Http\Controllers\OfflinePolicyUploadController::class, 'upload'])->name('api.renewal-data-upload-v2');
Route::post('bcl-wm/renewal-data-upload', [CommonController::class, 'renewalDataUpload'])->name('api.baja-wm.renewal-data-upload');
Route::post('renewal-migration-logs', [RenewalDataMigrationStatusController::class, 'logs']);
Route::post('ckyc-verifications', [CkycController::class, 'ckycVerifications']);
Route::post('ckyc-uploadDocs', [CkycController::class, 'ckycUploadDocuments']);

Route::post('GodigitKycStatus', [CommonController::class, 'GodigitKycStatus']);
Route::post('royalSundaramKycStatus', [CommonController::class, 'royalSundaramKycStatus']);
Route::post('TmibaslGetNsdlLink', [TmibaslNsdlApiController::class, 'getNsdlLink']);

Route::match(['get', 'post'], 'comparesms', [CommonController::class, 'comparePageSms']);
Route::post('generate-lead-dashboard', [CommonController::class, 'generateLeadByVehicleDetails']);

Route::post('ckycStatusUpdate', [\App\Http\Controllers\ckycStatusUpdate::class, 'ckycStatusUpdate']);

Route::post('run/migrate', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate');
    echo "<pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
});

Route::get('getAllBrokerName', [\App\Http\Controllers\CommonController::class, 'getAllBrokerName']);
//Route::post('getleads', [\App\Http\Controllers\LeadController::class, 'getleads']);
Route::match(['get', 'post'],'getleads', [\App\Http\Controllers\LeadController::class, 'getleads']);
Route::post('qr-lead-generation', [\App\Http\Controllers\LeadController::class, 'qrLeadGeneration']);   // For Hero dashboard
Route::post('hibl-lead-generation', [\App\Http\Controllers\LeadController::class, 'hiblleadgeneration']);   // For Hero dashboard new
Route::post('lead-generation', [\App\Http\Controllers\LeadController::class, 'leadGeneration']);   // For Bajaj unified dashboard
Route::post('updateTataAigCkycDetails', [\App\Http\Controllers\CommonController::class, 'updateTataAigCkycDetails']);

Route::match(['get', 'post'], 'tokenservice', [\App\Http\Controllers\TokenController::class, 'tokenService']);

Route::group([
    'prefix' => 'visibility',
], function () {
    Route::get('errors', [\App\Http\Controllers\ErrorVisibilityController::class, 'getVisibilityReport']);
    Route::get('report-count', [\App\Http\Controllers\ErrorVisibilityController::class, 'getVisibilityReportCount']);
    Route::get('report-count-new', \App\Http\Controllers\ErrorVisibilityControllerNew::class);
    Route::get('get-ckyc-count-summary', [\App\Http\Controllers\ErrorVisibilityController::class, 'getCkycCountSummary']);
});

Route::get('getcount', [App\Http\Controllers\Reports\ReportsController::class, 'GetCount']);

Route::get('getFaq', [App\Http\Controllers\CommonController::class, 'getFaq']);
Route::post('postFaq', [App\Http\Controllers\CommonController::class, 'postFaq']);

Route::post('qr-generation', [\App\Http\Controllers\GenerateQR\QRCodeController::class, 'generateQRCode']);

Route::post('reset-kyc-data', [App\Http\Controllers\Ckyc\CkycCommonController::class, 'resetKycData']);


Route::group([
    'prefix' => 'onepay'
], function () {
    Route::post('kmd-tr-status', [App\Http\Controllers\OnePay\OnePayController::class, 'kmdtransactionstatus']);
    Route::post('rehit-all', [App\Http\Controllers\OnePay\OnePayController::class, 'rehitAll']);
    Route::get('lead-generate', [App\Http\Controllers\GenerateLeadController::class, 'onePay']);

    Route::post('lead-status', [App\Http\Controllers\GenerateLeadController::class, 'onePayleadStatus']);
});

Route::group([
    'prefix' => 'bharat-benz'
], function () {
    Route::get('lead-generate', [App\Http\Controllers\GenerateLeadController::class, 'bharatBenz']);
});

Route::withoutMiddleware([\App\Http\Middleware\ValidateTokenHeaderRequest::class])->group(function () {
    Route::get('car/getdata/{slug}', [MmvController::class, 'mmv_sync'])->name('car.getdata.all');
    Route::get('car/getdata', [MmvController::class, 'getMmvData']);
    Route::get('syncRto/{slug}', [MmvController::class, 'syncRto']);
    Route::get('getRtoData', [MmvController::class, 'getRtoData']);
    Route::match(['get', 'post'], 'serverToServer', [ServerToServerController::class, 'serverToServer']);
    Route::get('pdf-create', function (Request $request) {
        $pdf_data = DB::table('comapare_pdf_data')->where('uuid', request()->key)->first('data')->data;
        return $pdf = \PDF::loadView('comparepdf', ['data' => json_decode($pdf_data, true)])->stream();
    });
    Route::get('vahan-log/{id}/{view?}', [GenericController::class, 'getVahanLog'])->name('api.vahan.view-download');
    Route::get('log/{type}/{id}/{view?}', [GenericController::class, 'getLog'])->name('api.logs.view-download');

    Route::group([
        'prefix' => 'mdm',
        'as' => 'mdm.',
    ], function () {
        Route::put('sync-all-masters', [App\Http\Controllers\MasterDataManagementController::class, 'syncAllMasters'])->name('sync.all.masters');
        Route::post('get-all-masters', [App\Http\Controllers\MasterDataManagementController::class, 'getAllMasters'])->name('get.all.masters');
        Route::put('sync-single-master/{master_id}', [App\Http\Controllers\MasterDataManagementController::class, 'syncSingleMaster'])->name('sync.single.master');
    });

    Route::group([
        'prefix' => 'ic-config',
        'as' => 'ic-config.',
    ], function() {
        Route::get('/listk', [IcConfiguratorController::class, 'fetching'])->name('listk');
        Route::get('/ProductList', [IcConfiguratorController::class, 'ProductList'])->name('ProductList');
        Route::get('/fetchingPremium', [IcConfiguratorController::class, 'fetchingPremium'])->name('fetchingPremium');
        Route::get('/fetchingProduct', [IcConfiguratorController::class, 'fetchingProduct'])->name('fetchingProduct');
    });

    Route::match(['get', 'post'],'getIcList', [CommonController::class, 'getIcList'])->name('api.getIcList');
    Route::post('tokenGeneration', [TokenGenerationController::class, 'generateToken']);
    Route::post('addProposalValidation', [ProposalValidation::class, 'addProposalValidation'])->name("api.addProposalValidation");
    Route::get('getProposalValidation', [ProposalValidation::class, 'getProposalValidation'])->name("api.getProposalValidation");

    Route::post('internal/icTokenGeneration/{company_alias}', [\App\Http\Controllers\IcTokenGenerationController::class, 'index'])->name('icTokenGeneration');
    Route::get('logRequest/{type}/{id}/{view?}', [LogController::class, 'getLog'])->name('api.logs.response');
    Route::post('logReqResponse',[LogController::class, 'LogReqResponse'])->name('api.logReqResponse');
    Route::post('logResponseDownload',[LogController::class, 'logDownload'])->name('api.logResponseDownload');
    Route::post('log-document-download',[LogController::class, 'documentDownload'])->name('api.log-document-download');
    Route::get('check-live', [CommonController::class, 'checkLive']);
    Route::post('getProposalFields', [ProposalValidation::class, 'getProposalFields']);

});

Route::match(['get', 'post'],'fetchOrSetCommunicationPreference', [App\Http\Controllers\CommunicationPreferenceController::class, 'index']);

Route::get('getIncorrectPolicyDetails', [\App\Http\Controllers\Reports\PolicyReportController::class, 'getIncorrectPolicyDetails']);
Route::post('agent_data_cleanup', [CommonController::class, 'agent_data_cleanup']);
Route::get('getTraceIds', [App\Http\Controllers\Reports\ReportsController::class, 'GetTraceIds']);
Route::post('sbi-document-upload', [CommonController::class, 'sbiDocumentUpload']);
Route::post('stageChangeViaEnquiryId', [CommonController::class, 'stageChangeViaEnquiryId']);
Route::post('checktotp', [App\Http\Controllers\Admin\UserController::class, 'checkTotp']);
Route::post('user/create', [App\Http\Controllers\Lte\Admin\UserController::class, 'createUsers']);

//Utility API
Route::group([
    'prefix' => 'utility'
], function () {
    Route::post('iciciLombardPosStatusUpdate', [App\Http\Controllers\Extra\UtilityApi::class, 'iciciLombardPosStatusUpdate']);
    Route::post('changeJourneyStage', [App\Http\Controllers\Extra\UtilityApi::class, 'changeJourneyStage']);
    Route::post('correctionReportApi', [App\Http\Controllers\Extra\UtilityApi::class, 'correctionReportApi']);
    Route::post('ChassisEngineCheck', [App\Http\Controllers\Extra\UtilityApi::class, 'ChassisEngineCheck']);
    Route::post('Pushlogsbyleadid', [App\Http\Controllers\Extra\PushLogs::class, 'PushLogsByLeadId']);
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
Route::group([
    'prefix' => 'pos-imd-config',
    'as' => 'pos-imd-config.'
], function () {
    Route::post('list', [App\Http\Controllers\Admin\PosConfigController::class, 'getPosConfig'])->name('fetch');
    Route::delete('{id}', [App\Http\Controllers\Admin\PosConfigController::class, 'destroyConfig'])->name('destroy');
    Route::post('store', [App\Http\Controllers\Admin\PosConfigController::class, 'storePosConfig'])->name('store');
    Route::get('pos', [App\Http\Controllers\Admin\PosConfigController::class, 'getPos'])->name('get-pos');
    Route::get('sections', [App\Http\Controllers\Admin\PosConfigController::class, 'getSections'])->name('get-sections');
    Route::get('ics', [App\Http\Controllers\Admin\PosConfigController::class, 'getIcs'])->name('get-ics');
    Route::get('fields', [App\Http\Controllers\Admin\PosConfigController::class, 'getFields'])->name('get-fields');
});

Route::group([
    'prefix' => 'posp-utility',
    'as' => 'posp-utility.',
], function () {
    Route::get('fetch-master', [App\Http\Controllers\PospUtility\FetchMasterController::class, 'fetchMaster']);
    Route::get('save-form', [App\Http\Controllers\PospUtility\FetchMasterController::class, 'save']);
    Route::post('add-ic-parameter', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'addIcParameters']);
    Route::post('add-mmv-utility', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'addMmvUtility']);
    Route::post('add-rto-utility', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'addRtoUtility']);
    Route::get('get-segments', [App\Http\Controllers\PospUtility\FetchMasterController::class, 'getSegmentList']);
    Route::get('get-ic-by-segment', [App\Http\Controllers\PospUtility\FetchMasterController::class, 'getIcbySegment']);
    Route::post('get-imd-mapping', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'getPospImdMapping']);
    Route::post('get-mmv-mapping', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'getMmvMapping']);
    Route::post('get-rto-mapping', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'getRtoMapping']);
    Route::post('update-record', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'updatePospUtility']);
    Route::post('add-imd-fields', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'addImd']);
    Route::post('update-imd-fields', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'updateImd']);
    Route::post('delete-imd-fields', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'deleteImd']);
    Route::post('imd-list', [App\Http\Controllers\PospUtility\PospUtilityController::class, 'listImd']);

});

Route::group([
    'prefix' => 'master',
    'as' => 'master.',
], function () {
    Route::post('rto', [CommonController::class, 'getRtoMaster']);
    Route::post('segment', [CommonController::class, 'getSegment']);
    Route::post('ic', [CommonController::class, 'getInsurersCompnay']);
});

//IC SAMPLING
Route::post('ic_sampling', [CommonController::class, 'IcSampling']);

// Log Rotation
Route::get('/s3_updload',[LogRotationController::class,'pushLogToS3']);

Route::group([
    'prefix' => 'premium-details'
], function () {
    Route::post('report', [\App\Http\Controllers\PremiumDetailController::class, 'premiumDetailReport']);
    Route::post('manual-sync', [\App\Http\Controllers\PremiumDetailController::class, 'premiumSync']);
});


Route::group([
    'prefix' => 'commission'
], function () {
    Route::get('rules', [\App\Http\Controllers\BrokerCommissionController::class, 'getCommissionRules']);
});

//Bank details fetch api 
Route::post('bankVerification', [App\Http\Controllers\AccountUtilities::class, 'getBankDetails']);
Route::post('commision-data-add', [CommisionConfiguratorController::class, 'store']);
Route::match(['get', 'post'],'startProcess', [QuoteService::class, 'startProcess']);
Route::post('getProductCount', [QuoteService::class, 'getProductCount']);

//Dashboard policy update api
Route::group([
    'prefix' => 'dashboard_action'
], function () {
    Route::post('update_policy_details', [\App\Http\Controllers\DashboardOnlineDataUpate::class, 'PolicyDetailsUpdate']);
    Route::post('update_policy_stage', [\App\Http\Controllers\DashboardOnlineDataUpate::class, 'PolicyStageUpdate']);
});

//Update Policy start date and End date api
Route::post('updatePolicyDates', [App\Http\Controllers\PolicyStartAndEndDateUpdate::class, 'updatePolicyDates']);
Route::post('getInspectionType', [InspectionTypeUtilityController::class, 'getInspectionType']);
Route::get('getpainsurance', [App\Http\Controllers\Lte\Admin\pa_insurance_masters::class,'getpainsurance']);

Route::post('crm-data-push', [App\Http\Controllers\CrmDataPushController::class, 'crmDataProcess']);

//vahan data inasert
Route::post('vahan-data-import', [CommonController::class, 'vahanDataImport']);
//vahan validation setting
Route::match(['get', 'post'],'132f0a931bccc0458941eec8e128b8d3',[CommonController::class, 'VahanConfigurationSetting']);
Route::post('getIcons',[CommonController::class, 'validatePolicy']);

//duplicate data change api
route::post('duplicate-data-change', [App\Http\Controllers\DuplicateDataChangeController::class, 'updateStageAndDeleteDuplicates']);

Route::group([
    'prefix' => 'v1'
], function () {
    Route::post('vahan-data-import', [CommonController::class, 'vahanDataImportV1']);
});

Route::get('ft-crm-journey', [\App\Http\Controllers\CrmLeadController::class, 'generateLead']);
Route::post('migrate-agent', [\App\Http\Controllers\AgentMigrationController::class, 'migrateAgent']);
//journey redirect through stage 
Route::post('redirect', [\App\Http\Controllers\RedirectStageController::class, 'redirect']); 

Route::post('send-communication-email', [\App\Http\Controllers\CommunicationEmailController::class, 'sendCommunicationEmail']);