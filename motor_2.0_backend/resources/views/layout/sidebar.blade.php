      <style>
        .nav{
            background-color: #ffffff !important;
            border-bottom-right-radius: 8px;
        }
        @media only screen and (min-width: 992px){
            #sidebar {
                position: static;
                top: 0;
                height: 100vh;
                overflow-y: auto;
            }      
        }
        .sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link, .sidebar .nav:not(.sub-menu) > .nav-item:hover[aria-expanded="true"] {
            background: var(--bs-primary);
            border-radius: 0px 8px 8px 0;
        }
        .sidebar .nav .nav-item.active > .nav-link i, .sidebar .nav .nav-item.active > .nav-link .menu-arrow {
            color: #ffffff !important;
        }
        .sidebar .nav .nav-item:hover > .nav-link i, .sidebar .nav .nav-item:hover > .nav-link .menu-arrow {
            color: #ffffff !important;
        }
       .sidebar .nav .nav-item.active > .nav-link .new-menu-title{
            color: #ffffff ;
        }
        .sidebar .nav .nav-item:hover > .nav-link .new-menu-title{
            color: #ffffff ;
        }
        .sidebar .nav.sub-menu .nav-item .nav-link:hover{
            color: var(--bs-primary) !important;
        }
        .sidebar .nav.sub-menu .nav-item::before{
            content: none !important;
        }
        .sidebar .nav .nav-item.active > .nav-link{
            background: var(--bs-primary) !important;
            color: #303030 !important;
            border-radius: 0px 8px 8px 0;
        }
        .menu-title{
            font-weight: bold !important;
        }
        .nav-item.active {
            font-weight: bold;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15)
        }
        .nav.sub-menu{
            border-radius: 10px;
        }
        .searchbarbox{
            display: flex;
            justify-content: center;
            /* max-width: 150px; */
            padding-left:10px;
            padding-right:10px;
        }
        .searchbar{
            padding: 5px;
            border-radius: 10px;
            border: 1px solid #727272;
        }
        .searchbar:focus, .searchbar:active{
            border: 1px solid var(--bs-primary) !important;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15)
        }
      </style>
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar" style="background:#ffffff;">
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.dashboard.index') }}">
                    <i class="mdi mdi-view-dashboard menu-icon"></i>
                    <span class="menu-title">Dashboard</span>
                </a>
            </li>
            <!-- <li class="nav-item nav-category">Admin</li> -->
            @if (auth()->user()->can('user.list') ||
                auth()->user()->can('role.list') ||
                auth()->user()->can('password_policy'))
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#user-management" aria-expanded="false"
                        aria-controls="user-management">
                        <i class="menu-icon mdi mdi-account-multiple"></i>
                        <span class="menu-title">User Management</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="user-management">
                        <ul class="nav flex-column sub-menu">
                            @can('user.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.user.index') }}">Admin User</a></li>
                            @endcan
                            @can('role.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.role.index') }}">Role</a>
                                </li>
                            @endcan
                            @can('password_policy')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.password-policy.index') }}">Password Policy</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endif

            @if (auth()->user()->can('company.list') ||
                auth()->user()->can('pos.list') ||
                auth()->user()->can('ckyc_not_a_failure_cases.list') ||
                auth()->user()->can('encrypt-decrypt.list') ||
                auth()->user()->can('payment.list') ||
                auth()->user()->can('rto_master.list') ||
                auth()->user()->can('ic-error-handling.list') ||
                auth()->user()->can('preferred_rto.list') ||
                auth()->user()->can('addon_configuration.list') ||
                auth()->user()->can('financing_agreement.list') ||
                auth()->user()->can('gender_mapping.list') ||
                auth()->user()->can('nominee_relationship.list') ||
                auth()->user()->can('rto_master.list') ||
                auth()->user()->can('master_occupation.list') ||
                auth()->user()->can('master_occuption_name.list') ||
                auth()->user()->can('third_party.list') ||
                auth()->user()->can('previous_insurer_mappping.list') ||
                auth()->user()->can('abibl_mg_data.list') ||
                auth()->user()->can('abibl_old_data.list') ||
                auth()->user()->can('abibl_hyundai_data.list') ||
                auth()->user()->can('user.list') ||
                auth()->user()->can('configuration.list') ||
                auth()->user()->can('log.list') ||
                auth()->user()->can('master_policy.list') ||
                auth()->user()->can('policy_wording.list') ||
                auth()->user()->can('usp.list') ||
                auth()->user()->can('previous_insurer.list') ||
                auth()->user()->can('manufacturer.list') ||
                auth()->user()->can('CashlessGarage.list') ||
                auth()->user()->can('mmv_data.list') ||
                auth()->user()->can('ic_configurator.view'))

                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
                            <i class="menu-icon mdi mdi-cogs"></i>
                            <span class="menu-title">Admin</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="ui-basic">
                            <ul class="nav flex-column sub-menu">
                                @can('company.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.company.index') }}">Company Logo</a></li>
                                    <!-- <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.vahan_service_dash.dash') }}">Vahan Service</a></li> -->
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.common-config') }}">Common Configurations</a></li>
                                @endcan
                                
                                @can('ckyc_not_a_failure_cases.list')
                                <li class="nav-item"> <a class="nav-link"
                                href="{{ route('admin.ckyc_not_a_failure_cases.index') }}">Ckyc Not A Failure Cases</a>
                                </li>
                                 @endcan

                                 @can('ckyc_not_a_failure_cases.list')
                                <li class="nav-item"> <a class="nav-link"
                                href="{{ route('admin.ckyc_verification_types.index') }}">Ckyc Verification Types</a>
                                </li>
                                 @endcan
                                
                                @can('frontend_constants.list')
                                <li class="nav-item"> 
                                    <a class="nav-link" href="{{ route('admin.frontend_index') }}">Frontend Constants</a>
                                </li>
                                @endcan

                                @can('configuration.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.configuration.index') }}">Configuration</a></li>
                                @endcan

                                @if (auth()->user()->can('user-journey-activities.clear'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('admin.user-journey-activity') }}">User Activity Session</a>
                                    </li>
                                @endif

                                @can('pos.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.pos-data.index') }}">Download POS
                                        Data</a>
                                </li>
                                @endcan

                                @can('pos.agents')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.pos-list') }}">POS Agents</a>
                                </li>
                                @endcan

                                @can('master_policy.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.master-product.index') }}">Master Product</a></li>
                                @endcan

                                @can('mmv_data.list')
                                    <li class="nav-item"> <a class="nav-link" href="{{ route('admin.mmv-data.index') }}">MMV
                                            Data</a></li>
                                @endcan

                                @can('manufacturer.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.manufacturer.index') }}">Manufacturer</a></li>
                                @endcan

                                @can('previous_insurer.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.previous-insurer.index') }}">PreviousInsurer</a></li>
                                @endcan

                                @can('preferred_rto.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.rto-prefered.index') }}">Preferred RTO</a></li>
                                @endcan

                                @can('policy_wording.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.policy-wording.index') }}">Policy Wording</a></li>
                                @endcan

                                @can('usp.list')
                                    <li class="nav-item"> <a class="nav-link" href="{{ route('admin.usp.index') }}">USP</a>
                                    </li>
                                    <li class="nav-item"> <a class="nav-link" href="{{ route('admin.broker.index') }}">Broker
                                            Details</a></li>
                                @endcan
                                {{-- this is not required  --}}
                                {{--  @can('addon_configuration.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.addon-config.index') }}">Addon Configuration</a></li>
                                @endcan  --}}

                                @can('financing_agreement.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.finance-agreement-master.index') }}">Financing agreement</a></li>
                                @endcan

                                @can('nominee_relationship.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.nominee-master.index') }}">Nominee relationship </a></li>
                                @endcan

                                @can('gender_mapping.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.gender-master.index') }}">Gender Mapping </a></li>
                                @endcan

                                {{-- payment response --}}
                                @can('payment.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.payment-log.index') }}">Payment Response</a></li>
                                @endcan

                                {{-- RTO Master --}}
                                @can('rto_master.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.rto-master.index') }}">RTO Master</a></li>
                                @endcan
                                @if (config('constants.motorConstant.SMS_FOLDER') === 'abibl')
                                @can('abibl_mg_data.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.abibl-data-migration.index') }}">ABIBL MG DATA
                                        Migration</a>
                                </li>
                                @endcan
                                
                                @can('abibl_old_data.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.abibl-data-migration-old.index') }}">ABIBL OLD DATA
                                        Migration</a>
                                </li>
                                @endcan
                                @can('abibl_hyundai_data.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.abibl-data-migration-hyundai.index') }}">
                                        Hyundai Data Upload</a>
                                </li>
                                @endcan
                                @endif

                                @if (config('constants.motorConstant.SMS_FOLDER') === 'gramcover')
                                    @can('user.list')
                                        <li class="nav-item"> <a class="nav-link" href="{{ url('api/getAgents') }}"
                                                target="_blank">Gramcover POS Data Sync</a></li>
                                    @endcan
                                @endif

                                @can('ic-error-handling.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.ic-error-handling.index') }}">IC Error Handler</a>
                                    </li>
                                @endcan

                                @can('encrypt-decrypt.list')
                                    <li class="nav-item">
                                        <a class="nav-link"
                                            href="{{ route('admin.encrypt-decrypt') }}">Encryption/Decryption</a>
                                    </li>
                                @endcan
                                @can('master_occupation.list')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.master-occuption.index') }}">Master Occupation</a>
                                    </li>
                                @endcan

                                @can('master_occuption_name.list')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.master-occupation-name.index') }}">Master Occupation Name</a>
                                    </li>
                                @endcan


                                @can('third_party.list')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.third_party_settings.index') }}">Third Party Settings</a>
                                    </li>
                                @endcan

                                @can('CashlessGarage.list')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.cashless_garage.index') }}">Cashless Garage</a>
                                    </li>
                                @endcan

                                {{-- @can('previous_insurer_mappping.list')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.previous-insurer-mapping.index') }}">Previous Insurer Mappping </a>
                                    </li>
                                @endcan --}}

                                @can('configurator.proposal')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.config-proposal-validation') }}">Master Configurator</a>
                                    </li>
                                @endcan     
                               
                                @can('configurator.pos-imd')
                                    <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.pos-config.home') }}">Pos Imd Configurator</a>
                                    </li>
                                @endcan

                                @can('ic_configurator.view')
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.ic-config.credential.index') }}">IC Configurator</a>
                                </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.template.index') }}">Template Master</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.common-configuration.index') }}">Communication Configurator</a>
                                </li>

                                @can('configurator.payment_gateway')
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.pg-config.home') }}">Payment Gateway Configurator</a>
                                </li>
                                @endcan

                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.ic-configuration.formula.create-formula') }}">IC Configurator</a>
                                </li>

                            </ul>

                        </div>
                    </li>
            @endif

            @if (auth()->user()->can('log.list') || 
            auth()->user()->can('journey_data.list') ||
            auth()->user()->can('ckyc_log.list') ||
            auth()->user()->can('ckyc_wrapper_log.list') ||
            auth()->user()->can('kafka_log.list')||
            auth()->user()->can('third_paty_payment.list') ||
            auth()->user()->can('push_api.list') ||
            auth()->user()->can('icici_master.list') ||
            auth()->user()->can('error_master.list') ||
            auth()->user()->can('trace_journey_id.list')||
            auth()->user()->can('third_party_api.list') ||
            auth()->user()->can('mongodb.list') ||
            auth()->user()->can('onepay_log.list') ||
            auth()->user()->can('ongrid_fastlane.list') ||
            auth()->user()->can('ola_whatsapp_log.list'))
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#logs" aria-expanded="false"
                        aria-controls="form-element2">
                        <i class="menu-icon mdi mdi-chart-line-stacked"></i>
                        <span class="menu-title">Logs</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="logs">
                        <ul class="nav flex-column sub-menu">

                            @can('log.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.log.index') }}">Logs</a>
                                </li>
                            @endcan

                            @can('log.server-log')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.server-log') }}">Server Errors</a>
                                </li>
                            @endcan

                            @can('log.renewal-api')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.renewal-data-logs.index') }}">Renewal Data Api Logs</a>
                                </li>
                            @endcan

                            @can('ckyc_log.list')
                            <li class="nav-item"> <a class="nav-link"
                                href="{{ route('admin.ckyc-logs.index') }}">Ckyc Logs</a>
                            </li>
                            @endcan

                            @can('ckyc_wrapper_log.list')
                            <li class="nav-item"> <a class="nav-link"
                                href="{{ route('admin.ckyc-wrapper-logs.index') }}">Ckyc Wrapper Logs</a>
                            </li>
                            @endcan

                            @if (auth()->user()->can('report.list'))
                                <li class="nav-item"> <a class="nav-link"
                                    href="{{ route('admin.stage-count') }}">Journey Stage Count</a>
                                </li>
                            @endcan

                            @if (config('constants.motorConstant.SMS_FOLDER') === 'renewbuy')
                                @can('kafka_log.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.kafka-logs.index') }}">Kafka Logs</a></li>

                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.kafka-sync-data') }}">Kafka Sync Statistics</a></li>
                                @endcan
                            @endif

                            @can('third_paty_payment.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.log.third-paty-payment') }}">Third Party Payment Logs</a>
                                </li>
                            @endcan

                            @can('journey_data.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.journey-data.index') }}">Journey Data</a>
                                </li>
                            @endcan

                            @can('push_api.list')
                                <li class="nav-item"> <a class="nav-link" href="{{ route('admin.push-api.index') }}">Push
                                    Api Data</a>
                                </li>
                            @endcan

                            @can('icici_master.list')
                                <li class="nav-item"> <a class="nav-link"
                                    href="{{ route('admin.icici-master.index') }}">IC Master Download</a>
                                </li>
                            @endcan

                            {{--<!-- @can('error_master.list')
                                <li class="nav-item"> <a class="nav-link"
                                    href="{{ route('admin.error-list-master.index') }}">Error List </a>
                                </li>
                            @endcan -->--}}

                            @can('trace_journey_id.list')
                                <li class="nav-item"> <a class="nav-link"
                                        href="{{ route('admin.trace-journey-id.index') }}">Get Trace ID</a>
                                </li>
                            @endcan

                            @can('third_party_api.list')
                                <li class="nav-item"> <a class="nav-link"
                                    href="{{ route('admin.third_party_api_request_responses.index') }}">Third Party Api Responses</a>
                                </li>
                            @endcan

                            @can('mongodb.list')
                            <li class="nav-item"> <a class="nav-link"
                                href="{{ route('admin.mongodb') }}">Dashboard Mongo Logs</a>
                             </li> 
                        @endcan

                            {{-- @if (config('constants.OnePaymentGateway.onepay.PAYMENT_TYPE') === 'ONEPAY') --}}
                                @can('onepay_log.list')
                                    <li class="nav-item"> <a class="nav-link" href="{{ route('admin.onepay-log') }}">Onepay Transaction Log</a>
                                    </li>
                                @endcan
                            {{-- @endif --}}

                            @if (config('constants.motorConstant.SMS_FOLDER') === 'ola')
                                @can('ongrid_fastlane.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.log.ongrid-fastlane') }}">OLA Ongrid
                                            {{-- Fastlane --}} Logs</a>
                                    </li>
                                @endcan
                                @can('ola_whatsapp_log.list')
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('admin.log.ola-whatsapp-log') }}">Ola Whatsapp
                                            {{-- Fastlane --}} Logs</a>
                                    </li>
                                @endcan
                            @endif

                            @can('datapushlog.list')
                                <li class="nav-item"> <a class="nav-link"
                                    href="{{ route('admin.datapush-logs') }}">Data Push Logs </a>
                                </li>
                            @endcan

                            @if (auth()->user()->can('discount.config') || auth()->user()->can('discount.config.activity-logs'))
                                @if (auth()->user()->can('discount.config'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.discount-configurations.config-setting') }}">Discount Configuration</a>
                                </li>
                                @endif
                                {{-- @if (auth()->user()->can('discount.config.activity-logs'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.discount-configurations.activity-logs') }}">Activity Logs</a>
                                </li>
                                @endif --}}
                            @endif

                            @if (auth()->user()->can('log.vahan-service'))
                                @can('log.vahan-service')
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('admin.vahan-service-logs.index') }}">Vahan Service Logs</a></li>
                                @endcan
                            @endif

                            @if(auth()->user()->can('log.user-activity'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.user-activity-logs.index') }}" >
                                    User Activity Logs
                                    </a>
                                </li>
                            @endif

                        </ul>
                    </div>
                </li>
            @endif

            @if (auth()->user()->can('report.list'))
                <!-- <li class="nav-item nav-category">Reports</li> -->
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#form-element2" aria-expanded="false"
                        aria-controls="form-element2">
                        <i class="menu-icon mdi mdi-clipboard-text"></i>
                        <span class="menu-title">Report</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="form-element2">
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link"
                                    href="{{ route('admin.report.index') }}">Policy
                                    Reports</a>
                            </li>
                            <li class="nav-item"><a class="nav-link"
                                href="{{ route('admin.rc-report.index') }}">RC Reports</a>
                            </li>
                                {{--<!-- <li class="nav-item"><a class="nav-link text-wrap"
                                    href="{{ route('admin.rc-report.proposal-validation') }}">Proposal Validation Reports</a> -->
                                <!-- </li> -->--}}
                            @if (config('constants.motor.IS_EMBEDDED_SCRUB_ENABLED') == 'Y')
                                <li class="nav-item"><a class="nav-link"
                                        href="{{ route('admin.embedded-scrub') }}">Embedded Scrub</a>
                                </li>
                            @endif
                            {{-- @if (auth()->user()->can('renewal-data-migration.report'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.renewal-data-migration.index') }}">Renewal Data Migration</a>
                                </li>
                            @endif --}}
                        </ul>
                    </div>
                </li>
            @endif

            @if (auth()->user()->can('master.sync.fetch') || auth()->user()->can('master.sync.logs'))
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#MDM-elements" aria-expanded="false" aria-controls="MDM-elements">
                        <i class="menu-icon mdi mdi-database-refresh"></i>
                        <span class="menu-title">Master Sync</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="MDM-elements">
                        <ul class="nav flex-column sub-menu">
                            @if (auth()->user()->can('master.sync.fetch'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.mdm.fetch.all.masters') }}">Fetch All Masters</a>
                            </li>
                            @endif
                            @if (auth()->user()->can('master.sync.logs'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.mdm.sync.logs') }}" aria-expanded="false">Sync logs</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                </li>
            @endif

            @if (auth()->user()->can('renewal_data_upload.view') || auth()->user()->can('renewal-data-migration.report') )
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#renewalDataupload" aria-expanded="false"
                        aria-controls="renewalDataupload">
                        <i class="menu-icon mdi mdi-upload"></i>
                        <span class="menu-title">Renewal Uploads</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="renewalDataupload">
                        <ul class="nav flex-column sub-menu">
                            @if(auth()->user()->can('renewal_data_upload.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.renewal_upload_excel_post') }}">
                                    Upload Excel
                                </a>
                            </li>
                             @endif
                            @if (auth()->user()->can('renewal-data-migration.report'))
                            <li class="nav-item">
                                <a class="nav-link text-wrap"
                                    href="{{ route('admin.renewal_upload_migration_logs') }}">View Renewal Migration logs
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>


            @endif
            @can('company.list')
            <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#vahan-basic" aria-expanded="false" aria-controls="vahan-basic">
                <i class="menu-icon mdi mdi-car"></i>
                <span class="menu-title">Vahan service</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="vahan-basic">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan_service.index') }}">Vahan Service</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan-service-credentials.index') }}">Vahan Service credentials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan-service-stage.stageIndex') }}">Vahan Service Configuration</a>
                    </li>
                </ul>
            </div>
        </li>
                
            @endcan

            @if (auth()->user()->can('sql_runner.view'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.sql-runner') }}">
                        <i class="menu-icon mdi mdi-xaml"></i>
                        <span class="menu-title">Run SQL</span>
                    </a>
                </li>
            @endif

        </ul>
      </nav>
      <script>
        const nav = document.querySelector('.nav');
        const menutitle = document.querySelector('.menu-title');
        const navToggleButton = document.getElementById('toggleButton');
        const elements = document.querySelectorAll('.menu-title');

        function handleNavWidthChange() {
            const divWidth = parseInt(window.getComputedStyle(nav).getPropertyValue('width'));

            if (divWidth === 70) {
                elements.forEach(element => {
                    element.classList.remove('new-menu-title'); // Remove the class when width is 70
                });
            } else {
                elements.forEach(element => {
                    element.classList.add('new-menu-title'); // Add the class when width is not 70
                });
            }
        }
        navToggleButton.addEventListener('click', function () {
            handleNavWidthChange();
        });
        nav.addEventListener('transitionend', function () {

            handleNavWidthChange();
        });
        document.addEventListener('DOMContentLoaded', function () {
            var navItems = document.querySelectorAll('.nav-item');

            navItems.forEach(function (navItem) {
                navItem.addEventListener('mouseover', function () {
                    // Mouse enter
                    if (this.classList.contains('hover-open')) {
                        var navsubItems = this.querySelector('.collapse');
                        if (navsubItems) {
                            navsubItems.style.overflowY = 'scroll';
                            navsubItems.style.maxHeight = '500px';
                        }
                    }
                });

                navItem.addEventListener('mouseout', function () {
                    // Mouse leave
                    var navsubItems = this.querySelector('.collapse');
                    if (navsubItems) {
                        navsubItems.style.overflowY = '';
                        navsubItems.style.maxHeight = '';
                    }
                });
            });
        });
      </script>
