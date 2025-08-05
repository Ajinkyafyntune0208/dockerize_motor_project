<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('menu_master')->truncate();
        // DB::table('menu_master')->insert([
        // $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menus = [
            [
                'menu_id' => 3,
                'menu_name' => 'Dashboard',
                'parent_id' => 0,
                'menu_slug' => 'dashboard',
                'menu_url' => '/admin/dashboard',
                'menu_icon' => '<i class="nav-icon fas fa-th"></i>'
            ],
            [ 
                'menu_id' => 4,
                'menu_name' => 'Operations',
                'parent_id' => 0,
                'menu_slug' => 'operations',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-edit"></i>'
            ],
            [
                'menu_id' => 5,
                'menu_name' => 'Logs',
                'parent_id' => 4,
                'menu_slug' => 'logs',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-chart-pie"></i>'
            ],
            [
                'menu_id' => 6,
                'menu_name' => 'Journey Data',
                'parent_id' => 5,
                'menu_slug' => 'journey_data',
                'menu_url' => '/admin/journey-data',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 7,
                'menu_name' => 'Payment Response',
                'parent_id' => 5,
                'menu_slug' => 'payment_response',
                'menu_url' => '/admin/payment-log',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 8,
                'menu_name' => 'Renewal Data Api Logs',
                'parent_id' => 5,
                'menu_slug' => 'renewal_data_api_logs',
                'menu_url' => '/admin/renewal-data-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 9,
                'menu_name' => 'Vahan Service Logs',
                'parent_id' => 5,
                'menu_slug' => 'vahan_service_logs',
                'menu_url' => '/admin/vahan-service-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 10,
                'menu_name' => 'Third Party Payment Logs',
                'parent_id' => 5,
                'menu_slug' => 'third_party_payment_logs',
                'menu_url' => '/admin/third-paty-payment',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 11,
                'menu_name' => 'Third Party Api Responses',
                'parent_id' => 5,
                'menu_slug' => 'third_party_api_responses',
                'menu_url' => '/admin/third_party_api_request_responses',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 12,
                'menu_name' => 'Onepay Transaction Log',
                'parent_id' => 5,
                'menu_slug' => 'onepay_transaction_log',
                'menu_url' => '/admin/onepay-log',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 13,
                'menu_name' => 'Data Push Logs',
                'parent_id' => 5,
                'menu_slug' => 'data_push_logs',
                'menu_url' => '/admin/datapush-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 14,
                'menu_name' => 'Dashboard Mongo Logs',
                'parent_id' => 5,
                'menu_slug' => 'dashboard_mongo_logs',
                'menu_url' => '/admin/mongodb',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 15,
                'menu_name' => 'Push Api Data',
                'parent_id' => 5,
                'menu_slug' => 'push_api_data',
                'menu_url' => '/admin/push-api',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 16,
                'menu_name' => 'Ckyc Logs',
                'parent_id' => 5,
                'menu_slug' => 'ckyc_logs',
                'menu_url' => '/admin/ckyc-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 17,
                'menu_name' => 'Ckyc Wrapper Logs',
                'parent_id' => 5,
                'menu_slug' => 'ckyc_wrapper_logs',
                'menu_url' => '/admin/ckyc-wrapper-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 18,
                'menu_name' => 'Server Errors',
                'parent_id' => 5,
                'menu_slug' => 'server_errors',
                'menu_url' => '/admin/server-error-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 19,
                'menu_name' => 'Kafka Logs',
                'parent_id' => 5,
                'menu_slug' => 'kafka_logs',
                'menu_url' => '/admin/kafka-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 20,
                'menu_name' => 'Reports',
                'parent_id' => 4,
                'menu_slug' => 'reports',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-tree"></i>'
            ],
            [
                'menu_id' => 21,
                'menu_name' => 'Policy Report',
                'parent_id' => 20,
                'menu_slug' => 'policy_report',
                'menu_url' => '/admin/report',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 22,
                'menu_name' => 'RC Report',
                'parent_id' => 20,
                'menu_slug' => 'rc_report',
                'menu_url' => '/admin/rc-report',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 23,
                'menu_name' => 'Renewal Upload',
                'parent_id' => 4,
                'menu_slug' => 'renewal_upload',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 24,
                'menu_name' => 'Upload Data',
                'parent_id' => 23,
                'menu_slug' => 'upload_data',
                'menu_url' => '/admin/renewal-upload-excel',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 25,
                'menu_name' => 'View Upload Process Logs',
                'parent_id' => 23,
                'menu_slug' => 'view_upload_process_logs',
                'menu_url' => '/admin/renewal-data-migration',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 26,
                'menu_name' => 'IC Configuration',
                'parent_id' => 4,
                'menu_slug' => 'ic_configuration',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon far fa-image"></i>'
            ],
            [
                'menu_id' => 27,
                'menu_name' => 'Master Product',
                'parent_id' => 26,
                'menu_slug' => 'master_product',
                'menu_url' => '/admin/master-product',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 28,
                'menu_name' => 'Discount Configuration',
                'parent_id' => 26,
                'menu_slug' => 'discount_configuration',
                'menu_url' => '/admin/discount-configurations/config-setting',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 29,
                'menu_name' => 'Misc',
                'parent_id' => 4,
                'menu_slug' => 'misc',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-copy"></i>'
            ],
            [
                'menu_id' => 31,
                'menu_name' => 'Journey Stage Count',
                'parent_id' => 29,
                'menu_slug' => 'journey_stage_count',
                'menu_url' => '/admin/stage-count',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 32,
                'menu_name' => 'IC Master Download',
                'parent_id' => 29,
                'menu_slug' => 'ic_master_download',
                'menu_url' => '/admin/ic-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 33,
                'menu_name' => 'Get Trace ID',
                'parent_id' => 29,
                'menu_slug' => 'get_trace_id',
                'menu_url' => '/admin/trace-journey-id',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 34,
                'menu_name' => 'User Activity Session',
                'parent_id' => 29,
                'menu_slug' => 'user_activity_session',
                'menu_url' => '/admin/user-journey-activity',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 35,
                'menu_name' => 'Download POS Data',
                'parent_id' => 29,
                'menu_slug' => 'download_pos_data',
                'menu_url' => '/admin/pos-data',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 36,
                'menu_name' => 'Encryption/Decryption',
                'parent_id' => 29,
                'menu_slug' => 'encryption_decryption',
                'menu_url' => '/admin/encrypt-decrypt',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 37,
                'menu_name' => 'Manage',
                'parent_id' => 0,
                'menu_slug' => 'manage',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-tachometer-alt"></i>'
            ],
            [
                'menu_id' => 38,
                'menu_name' => 'Configuration',
                'parent_id' => 37,
                'menu_slug' => 'configuration',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon far fa-image"></i>'
            ],
            [
                'menu_id' => 39,
                'menu_name' => 'Masters',
                'parent_id' => 37,
                'menu_slug' => 'masters',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-copy"></i>'
            ],
            [
                'menu_id' => 40,
                'menu_name' => 'Vahan Service Configurator',
                'parent_id' => 37,
                'menu_slug' => 'vahan_service_configurator',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-edit"></i>'
            ],
            [
                'menu_id' => 41,
                'menu_name' => 'User Management',
                'parent_id' => 37,
                'menu_slug' => 'User Management',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 42,
                'menu_name' => 'Menu',
                'parent_id' => 41,
                'menu_slug' => 'menu',
                'menu_url' => '/admin/menu',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 43,
                'menu_name' => 'RTO Master',
                'parent_id' => 39,
                'menu_slug' => 'rto_master',
                'menu_url' => '/admin/rto-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 44,
                'menu_name' => 'MDM Fetch All Masters',
                'parent_id' => 39,
                'menu_slug' => 'mdm_fetch_masters',
                'menu_url' => '/admin/fetch-all-masters',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 45,
                'menu_name' => 'MDM Sync logs',
                'parent_id' => 39,
                'menu_slug' => 'mdm_sync_logs',
                'menu_url' => '/admin/sync-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 46,
                'menu_name' => 'Master Occupation Name',
                'parent_id' => 39,
                'menu_slug' => 'master_occupation_name',
                'menu_url' => '/admin/master-occupation-name',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 47,
                'menu_name' => 'Master Occupation',
                'parent_id' => 39,
                'menu_slug' => 'master_occupation',
                'menu_url' => '/admin/master-occupation',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 48,
                'menu_name' => 'Cashless Garage',
                'parent_id' => 39,
                'menu_slug' => 'cashless_garage',
                'menu_url' => '/admin/cashless_garage',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 49,
                'menu_name' => 'Vahan Service',
                'parent_id' => 40,
                'menu_slug' => 'vahan_service',
                'menu_url' => '/admin/vahan_service',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 50,
                'menu_name' => 'Vahan Credentials',
                'parent_id' => 40,
                'menu_slug' => 'vahan_credentials',
                'menu_url' => '/admin/vahan-service-credentials',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 51,
                'menu_name' => 'Vahan Configuration',
                'parent_id' => 40,
                'menu_slug' => 'vahan_configuration',
                'menu_url' => '/admin/vahan-service-stage',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 52,
                'menu_name' => 'Users',
                'parent_id' => 41,
                'menu_slug' => 'user',
                'menu_url' => '/admin/user',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 53,
                'menu_name' => 'Role',
                'parent_id' => 41,
                'menu_slug' => 'role',
                'menu_url' => '/admin/role',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 54,
                'menu_name' => 'Password Policy',
                'parent_id' => 41,
                'menu_slug' => 'password_policy',
                'menu_url' => '/admin/password-policy',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 55,
                'menu_name' => 'Broker Details',
                'parent_id' => 38,
                'menu_slug' => 'broker_details',
                'menu_url' => '/admin/broker',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 56,
                'menu_name' => 'System Configuration',
                'parent_id' => 38,
                'menu_slug' => 'system_configuration',
                'menu_url' => '/admin/configuration',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 57,
                'menu_name' => 'Master Configurator',
                'parent_id' => 38,
                'menu_slug' => 'master_configurator',
                'menu_url' => '/admin/config-proposal-validation',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 58,
                'menu_name' => 'Insurance Company',
                'parent_id' => 38,
                'menu_slug' => 'insurance_company',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 59,
                'menu_name' => 'Manage IC Logo',
                'parent_id' => 58,
                'menu_slug' => 'manage_ic_logo',
                'menu_url' => '/admin/company',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 60,
                'menu_name' => 'Previous Insurer Logos',
                'parent_id' => 58,
                'menu_slug' => 'previous_insurer_logos',
                'menu_url' => '/admin/previous-insurer',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 61,
                'menu_name' => 'IC Error Handler',
                'parent_id' => 58,
                'menu_slug' => 'ic_error_handler',
                'menu_url' => '/admin/ic-error-handling',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 62,
                'menu_name' => 'Policy Wording',
                'parent_id' => 58,
                'menu_slug' => 'policy_wording',
                'menu_url' => '/admin/policy-wording',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 63,
                'menu_name' => 'Financing Agreement',
                'parent_id' => 58,
                'menu_slug' => 'financing_agreement',
                'menu_url' => '/admin/finance-agreement-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 64,
                'menu_name' => 'USP',
                'parent_id' => 58,
                'menu_slug' => 'usp',
                'menu_url' => '/admin/usp',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 65,
                'menu_name' => 'CKYC',
                'parent_id' => 38,
                'menu_slug' => 'ckyc',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 66,
                'menu_name' => 'Ckyc Not A Failure Cases',
                'parent_id' => 65,
                'menu_slug' => 'ckyc_not_a_failure_cases',
                'menu_url' => '/admin/ckyc_not_a_failure_cases',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 67,
                'menu_name' => 'Ckyc Verification Types',
                'parent_id' => 65,
                'menu_slug' => 'ckyc_verification_types',
                'menu_url' => '/admin/ckyc_verification_types',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 68,
                'menu_name' => 'Configurations',
                'parent_id' => 38,
                'menu_slug' => 'configurations',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 69,
                'menu_name' => 'Common Configurations',
                'parent_id' => 68,
                'menu_slug' => 'common_configurations',
                'menu_url' => '/admin/common-config',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 70,
                'menu_name' => 'Nominee Relationship',
                'parent_id' => 68,
                'menu_slug' => 'nominee_relationship',
                'menu_url' => '/admin/nominee-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 71,
                'menu_name' => 'Preferred RTO',
                'parent_id' => 68,
                'menu_slug' => 'preferred_rto',
                'menu_url' => '/admin/rto-prefered',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 72,
                'menu_name' => 'Manufacturer',
                'parent_id' => 68,
                'menu_slug' => 'manufacturer',
                'menu_url' => '/admin/manufacturer',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 73,
                'menu_name' => 'Gender Mapping',
                'parent_id' => 68,
                'menu_slug' => 'gender_mapping',
                'menu_url' => '/admin/gender-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 74,
                'menu_name' => 'Third Party Settings',
                'parent_id' => 68,
                'menu_slug' => 'third_party_settings',
                'menu_url' => '/admin/third_party_settings',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 75,
                'menu_name' => 'Template Master',
                'parent_id' => 68,
                'menu_slug' => 'template_master',
                'menu_url' => '/admin/template',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 76,
                'menu_name' => 'Query Builder',
                'parent_id' => 37,
                'menu_slug' => 'query_builder',
                'menu_url' => '/admin/sql-runner',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],
            [
                'menu_id' => 77,
                'menu_name' => 'IC Configurator',
                'parent_id' => 58,
                'menu_slug' => 'ic_configurator',
                'menu_url' => '/admin/ic-config/credential',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 78,
                'menu_name' => 'User Activity Logs',
                'parent_id' => 41,
                'menu_slug' => 'user_activity_logs',
                'menu_url' => '/admin/user-activity-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 79,
                'menu_name' => 'Authorization Request',
                'parent_id' => 29,
                'menu_slug' => 'authorization_request',
                'menu_url' => '/admin/authorization_requests',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 80,
                'menu_name' => 'log',
                'parent_id' => 5,
                'menu_slug' => 'log',
                'menu_url' => '/admin/log',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 81,
                'menu_name' => 'Premium Calculations',
                'parent_id' => 26,
                'menu_slug' => 'ic_config',
                'menu_url' => '#',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 82,
                'menu_name' => 'Label and Attributes',
                'parent_id' => 81,
                'menu_slug' => 'label_and_attribute',
                'menu_url' => '/admin/ic-configuration/label-attributes',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 83,
                'menu_name' => 'Configured ICs',
                'parent_id' => 81,
                'menu_slug' => 'premium_calculation_configurator',
                'menu_url' => '/admin/ic-configuration/premium-calculation-configurator',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 84,
                'menu_name' => 'View Attributes',
                'parent_id' => 81,
                'menu_slug' => 'view_attributes',
                'menu_url' => '/admin/ic-configuration/view_attribute',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 85,
                'menu_name' => 'IC Version Configurator',
                'parent_id' => 81,
                'menu_slug' => 'ic_version_configurator',
                'menu_url' => '/admin/ic-configuration/version/ic-version-configurator',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 86,
                'menu_name' => 'Formulas',
                'parent_id' => 81,
                'menu_slug' => 'formulas',
                'menu_url' => '/admin/ic-configuration/formulas',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 87,
                'menu_name' => 'IC Placeholder',
                'parent_id' => 81,
                'menu_slug' => 'ic_placeholder',
                'menu_url' => '/admin/ic-configuration/placeholder/ic-placeholder',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 88,
                'menu_name' => 'Buckets',
                'parent_id' => 81,
                'menu_slug' => 'buckets',
                'menu_url' => '/admin/ic-configuration/buckets',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 89,
                'menu_name' => 'POS Agents',
                'parent_id' => 29,
                'menu_slug' => 'download_pos_data',
                'menu_url' => '/admin/pos_agents',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 90,
                'menu_name' => 'ICICI Master Download',
                'parent_id' => 5,
                'menu_slug' => 'icici-master',
                'menu_url' => '/admin/icici-master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 91,
                'menu_name' => 'MMV DATA',
                'parent_id' => 5,
                'menu_slug' => 'mmv_data',
                'menu_url' => '/admin/mmv-data',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 92,
                'menu_name' => 'Frontend-Constant',
                'parent_id' => 5,
                'menu_slug' => 'frontend-constant',
                'menu_url' => '/admin/frontend-constant',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 93,
                'menu_name' => 'Payment Gateway Configuration',
                'parent_id' => 5,
                'menu_slug' => 'payment_gateway_configuration',
                'menu_url' => '/admin/pg-config',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 94,
                'menu_name' => 'POS IMD Configurator',
                'parent_id' => 5,
                'menu_slug' => 'pos_imd_configurator',
                'menu_url' => '/admin/pos-config',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 95,
                'menu_name' => 'Sync Brokerage Logs',
                'parent_id' => 5,
                'menu_slug' => 'sync_brokerage_logs',
                'menu_url' => '/admin/BrokerageLogs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 96,
                'menu_name' => 'Manufacturer Selector',
                'parent_id' => 37,
                'menu_slug' => 'make_selector',
                'menu_url' => '/admin/make_selector',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 97,
                'menu_name' => 'Product Type',
                'parent_id' => 4,
                'menu_slug' => 'product_type',
                'menu_url' => '/admin/master_product_type',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 98,
                'menu_name' => 'Queue Management',
                'parent_id' => 29,
                'menu_slug' => 'queue_management',
                'menu_url' => '/admin/queue-management',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 99,
                'menu_name' => 'Ckyc Redirection Logs',
                'parent_id' => 5,
                'menu_slug' => 'ckyc_redirection_logs',
                'menu_url' => '/admin/ckyc-redirection-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 100,
                'menu_name' => 'vahan journey configurations',
                'parent_id' => 40,
                'menu_slug' => 'vahan_journey_configurations',
                'menu_url' => '/admin/vahan-journey-config',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 101,
                'menu_name' => 'Commission Api Logs',
                'parent_id' => 5,
                'menu_slug' => 'commission_api_logs',
                'menu_url' => '/admin/commission-api-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 102,
                'menu_name' => 'POS',
                'parent_id' => 4,
                'menu_slug' => 'pos',
                'menu_url' => '#',
                'menu_icon' => '<i class="nav-icon fas fa-chart-pie"></i>'
            ],
            [
                'menu_id' => 103,
                'menu_name' => 'Pos service logs',
                'parent_id' => 102,
                'menu_slug' => 'pos_service_logs',
                'menu_url' => '/admin/pos-service-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 104,
                'menu_name' => 'Manufacturer Priority List',
                'parent_id' => 29,
                'menu_slug' => 'manufacturer_priority_list',
                'menu_url' => '/admin/manufacturer-priority',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => 120,
                'menu_name' => 'Allow Previous Insurers On Quote Landing',
                'parent_id' => 37,
                'menu_slug' => 'hide_pyi',
                'menu_url' => '/admin/hide_pyi',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
            ],

        ];
     
    // foreach ($menus as $menu) {
    //     if (!in_array($menu['menu_slug'], $existingMenus)) {
    //         DB::table('menu_master')->insert($menu);
    //     }

    // }
    
    DB::table('menu_master')->insert($menus);
}
}
