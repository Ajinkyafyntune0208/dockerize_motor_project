<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if(\App\Models\User::count() == 0){
            \App\Models\User::create([
                'name' => "Fyntune Admin",
                'email' => 'motor@fyntune.com',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'), // password
                'remember_token' => \Illuminate\Support\Str::random(10),
            ]);
        }

        $permissions = [
            ['name' => 'dashboard.list'],
            ['name' => 'dashboard.show'],
            ['name' => 'dashboard.create'],
            ['name' => 'dashboard.edit'],
            ['name' => 'dashboard.delete'],

            ['name' => 'journey_data.list'],
            ['name' => 'journey_data.show'],
            ['name' => 'journey_data.create'],
            ['name' => 'journey_data.edit'],
            ['name' => 'journey_data.delete'],

            ['name' => 'payment_response.list'],
            ['name' => 'payment_response.show'],
            ['name' => 'payment_response.create'],
            ['name' => 'payment_response.edit'],
            ['name' => 'payment_response.delete'],
            
            ['name' => 'renewal_data_api_logs.list'],
            ['name' => 'renewal_data_api_logs.show'],
            ['name' => 'renewal_data_api_logs.create'],
            ['name' => 'renewal_data_api_logs.edit'],
            ['name' => 'renewal_data_api_logs.delete'],
            
            ['name' => 'vahan_service_logs.list'],
            ['name' => 'vahan_service_logs.show'],
            ['name' => 'vahan_service_logs.create'],
            ['name' => 'vahan_service_logs.edit'],
            ['name' => 'vahan_service_logs.delete'],
            
            ['name' => 'third_party_payment_logs.list'],
            ['name' => 'third_party_payment_logs.show'],
            ['name' => 'third_party_payment_logs.create'],
            ['name' => 'third_party_payment_logs.edit'],
            ['name' => 'third_party_payment_logs.delete'],
            
            ['name' => 'third_party_api_responses.list'],
            ['name' => 'third_party_api_responses.show'],
            ['name' => 'third_party_api_responses.create'],
            ['name' => 'third_party_api_responses.edit'],
            ['name' => 'third_party_api_responses.delete'],
            
            ['name' => 'onepay_transaction_log.list'],
            ['name' => 'onepay_transaction_log.show'],
            ['name' => 'onepay_transaction_log.create'],
            ['name' => 'onepay_transaction_log.edit'],
            ['name' => 'onepay_transaction_log.delete'],
            
            ['name' => 'data_push_logs.list'],
            ['name' => 'data_push_logs.show'],
            ['name' => 'data_push_logs.create'],
            ['name' => 'data_push_logs.edit'],
            ['name' => 'data_push_logs.delete'],
            
            ['name' => 'dashboard_mongo_logs.list'],
            ['name' => 'dashboard_mongo_logs.show'],
            ['name' => 'dashboard_mongo_logs.create'],
            ['name' => 'dashboard_mongo_logs.edit'],
            ['name' => 'dashboard_mongo_logs.delete'],
            
            ['name' => 'push_api_data.list'],
            ['name' => 'push_api_data.show'],
            ['name' => 'push_api_data.create'],
            ['name' => 'push_api_data.edit'],
            ['name' => 'push_api_data.delete'],
            
            ['name' => 'ckyc_logs.list'],
            ['name' => 'ckyc_logs.show'],
            ['name' => 'ckyc_logs.create'],
            ['name' => 'ckyc_logs.edit'],
            ['name' => 'ckyc_logs.delete'],
            
            ['name' => 'ckyc_wrapper_logs.list'],
            ['name' => 'ckyc_wrapper_logs.show'],
            ['name' => 'ckyc_wrapper_logs.create'],
            ['name' => 'ckyc_wrapper_logs.edit'],
            ['name' => 'ckyc_wrapper_logs.delete'],
            
            ['name' => 'server_errors.list'],
            ['name' => 'server_errors.show'],
            ['name' => 'server_errors.create'],
            ['name' => 'server_errors.edit'],
            ['name' => 'server_errors.delete'],
            
            ['name' => 'kafka_logs.list'],
            ['name' => 'kafka_logs.show'],
            ['name' => 'kafka_logs.create'],
            ['name' => 'kafka_logs.edit'],
            ['name' => 'kafka_logs.delete'],
            
            ['name' => 'policy_report.list'],
            ['name' => 'policy_report.show'],
            ['name' => 'policy_report.create'],
            ['name' => 'policy_report.edit'],
            ['name' => 'policy_report.delete'],
            
            ['name' => 'rc_report.list'],
            ['name' => 'rc_report.show'],
            ['name' => 'rc_report.create'],
            ['name' => 'rc_report.edit'],
            ['name' => 'rc_report.delete'],
            
            ['name' => 'upload_data.list'],
            ['name' => 'upload_data.show'],
            ['name' => 'upload_data.create'],
            ['name' => 'upload_data.edit'],
            ['name' => 'upload_data.delete'],
            
            ['name' => 'view_upload_process_logs.list'],
            ['name' => 'view_upload_process_logs.show'],
            ['name' => 'view_upload_process_logs.create'],
            ['name' => 'view_upload_process_logs.edit'],
            ['name' => 'view_upload_process_logs.delete'],
            
            ['name' => 'master_product.list'],
            ['name' => 'master_product.show'],
            ['name' => 'master_product.create'],
            ['name' => 'master_product.edit'],
            ['name' => 'master_product.delete'],
            
            ['name' => 'discount_configuration.list'],
            ['name' => 'discount_configuration.show'],
            ['name' => 'discount_configuration.create'],
            ['name' => 'discount_configuration.edit'],
            ['name' => 'discount_configuration.delete'],
            
            ['name' => 'mmv_data.list'],
            ['name' => 'mmv_data.show'],
            ['name' => 'mmv_data.create'],
            ['name' => 'mmv_data.edit'],
            ['name' => 'mmv_data.delete'],
            
            ['name' => 'journey_stage_count.list'],
            ['name' => 'journey_stage_count.show'],
            ['name' => 'journey_stage_count.create'],
            ['name' => 'journey_stage_count.edit'],
            ['name' => 'journey_stage_count.delete'],
            
            ['name' => 'ic_master_download.list'],
            ['name' => 'ic_master_download.show'],
            ['name' => 'ic_master_download.create'],
            ['name' => 'ic_master_download.edit'],
            ['name' => 'ic_master_download.delete'],
            
            ['name' => 'get_trace_id.list'],
            ['name' => 'get_trace_id.show'],
            ['name' => 'get_trace_id.create'],
            ['name' => 'get_trace_id.edit'],
            ['name' => 'get_trace_id.delete'],
            
            ['name' => 'user_activity_session.list'],
            ['name' => 'user_activity_session.show'],
            ['name' => 'user_activity_session.create'],
            ['name' => 'user_activity_session.edit'],
            ['name' => 'user_activity_session.delete'],
            
            ['name' => 'download_pos_data.list'],
            ['name' => 'download_pos_data.show'],
            ['name' => 'download_pos_data.create'],
            ['name' => 'download_pos_data.edit'],
            ['name' => 'download_pos_data.delete'],
            
            ['name' => 'encryption_decryption.list'],
            ['name' => 'encryption_decryption.show'],
            ['name' => 'encryption_decryption.create'],
            ['name' => 'encryption_decryption.edit'],
            ['name' => 'encryption_decryption.delete'],
            
            ['name' => 'menu.list'],
            ['name' => 'menu.show'],
            ['name' => 'menu.create'],
            ['name' => 'menu.edit'],
            ['name' => 'menu.delete'],
            
            ['name' => 'rto_master.list'],
            ['name' => 'rto_master.show'],
            ['name' => 'rto_master.create'],
            ['name' => 'rto_master.edit'],
            ['name' => 'rto_master.delete'],
            
            ['name' => 'mdm_fetch_masters.list'],
            ['name' => 'mdm_fetch_masters.show'],
            ['name' => 'mdm_fetch_masters.create'],
            ['name' => 'mdm_fetch_masters.edit'],
            ['name' => 'mdm_fetch_masters.delete'],
            
            ['name' => 'mdm_sync_logs.list'],
            ['name' => 'mdm_sync_logs.show'],
            ['name' => 'mdm_sync_logs.create'],
            ['name' => 'mdm_sync_logs.edit'],
            ['name' => 'mdm_sync_logs.delete'],
            
            ['name' => 'master_occupation_name.list'],
            ['name' => 'master_occupation_name.show'],
            ['name' => 'master_occupation_name.create'],
            ['name' => 'master_occupation_name.edit'],
            ['name' => 'master_occupation_name.delete'],
            
            ['name' => 'master_occupation.list'],
            ['name' => 'master_occupation.show'],
            ['name' => 'master_occupation.create'],
            ['name' => 'master_occupation.edit'],
            ['name' => 'master_occupation.delete'],
            
            ['name' => 'cashless_garage.list'],
            ['name' => 'cashless_garage.show'],
            ['name' => 'cashless_garage.create'],
            ['name' => 'cashless_garage.edit'],
            ['name' => 'cashless_garage.delete'],
            
            ['name' => 'vahan_service.list'],
            ['name' => 'vahan_service.show'],
            ['name' => 'vahan_service.create'],
            ['name' => 'vahan_service.edit'],
            ['name' => 'vahan_service.delete'],
            
            ['name' => 'vahan_credentials.list'],
            ['name' => 'vahan_credentials.show'],
            ['name' => 'vahan_credentials.create'],
            ['name' => 'vahan_credentials.edit'],
            ['name' => 'vahan_credentials.delete'],
            
            ['name' => 'vahan_configuration.list'],
            ['name' => 'vahan_configuration.show'],
            ['name' => 'vahan_configuration.create'],
            ['name' => 'vahan_configuration.edit'],
            ['name' => 'vahan_configuration.delete'],
            
            ['name' => 'user.list'],
            ['name' => 'user.show'],
            ['name' => 'user.create'],
            ['name' => 'user.edit'],
            ['name' => 'user.delete'],
            
            ['name' => 'role.list'],
            ['name' => 'role.show'],
            ['name' => 'role.create'],
            ['name' => 'role.edit'],
            ['name' => 'role.delete'],
            
            ['name' => 'password_policy.list'],
            ['name' => 'password_policy.show'],
            ['name' => 'password_policy.create'],
            ['name' => 'password_policy.edit'],
            ['name' => 'password_policy.delete'],
            
            ['name' => 'broker_details.list'],
            ['name' => 'broker_details.show'],
            ['name' => 'broker_details.create'],
            ['name' => 'broker_details.edit'],
            ['name' => 'broker_details.delete'],
            
            ['name' => 'system_configuration.list'],
            ['name' => 'system_configuration.show'],
            ['name' => 'system_configuration.create'],
            ['name' => 'system_configuration.edit'],
            ['name' => 'system_configuration.delete'],
            
            ['name' => 'master_configurator.list'],
            ['name' => 'master_configurator.show'],
            ['name' => 'master_configurator.create'],
            ['name' => 'master_configurator.edit'],
            ['name' => 'master_configurator.delete'],
            
            ['name' => 'manage_ic_logo.list'],
            ['name' => 'manage_ic_logo.show'],
            ['name' => 'manage_ic_logo.create'],
            ['name' => 'manage_ic_logo.edit'],
            ['name' => 'manage_ic_logo.delete'],
            
            ['name' => 'previous_insurer_logos.list'],
            ['name' => 'previous_insurer_logos.show'],
            ['name' => 'previous_insurer_logos.create'],
            ['name' => 'previous_insurer_logos.edit'],
            ['name' => 'previous_insurer_logos.delete'],
            
            ['name' => 'ic_error_handler.list'],
            ['name' => 'ic_error_handler.show'],
            ['name' => 'ic_error_handler.create'],
            ['name' => 'ic_error_handler.edit'],
            ['name' => 'ic_error_handler.delete'],
            
            ['name' => 'policy_wording.list'],
            ['name' => 'policy_wording.show'],
            ['name' => 'policy_wording.create'],
            ['name' => 'policy_wording.edit'],
            ['name' => 'policy_wording.delete'],
            
            ['name' => 'financing_agreement.list'],
            ['name' => 'financing_agreement.show'],
            ['name' => 'financing_agreement.create'],
            ['name' => 'financing_agreement.edit'],
            ['name' => 'financing_agreement.delete'],
            
            ['name' => 'usp.list'],
            ['name' => 'usp.show'],
            ['name' => 'usp.create'],
            ['name' => 'usp.edit'],
            ['name' => 'usp.delete'],
            
            ['name' => 'ckyc_not_a_failure_cases.list'],
            ['name' => 'ckyc_not_a_failure_cases.show'],
            ['name' => 'ckyc_not_a_failure_cases.create'],
            ['name' => 'ckyc_not_a_failure_cases.edit'],
            ['name' => 'ckyc_not_a_failure_cases.delete'],
            
            ['name' => 'ckyc_verification_types.list'],
            ['name' => 'ckyc_verification_types.show'],
            ['name' => 'ckyc_verification_types.create'],
            ['name' => 'ckyc_verification_types.edit'],
            ['name' => 'ckyc_verification_types.delete'],
            
            ['name' => 'common_configurations.list'],
            ['name' => 'common_configurations.show'],
            ['name' => 'common_configurations.create'],
            ['name' => 'common_configurations.edit'],
            ['name' => 'common_configurations.delete'],
            
            ['name' => 'nominee_relationship.list'],
            ['name' => 'nominee_relationship.show'],
            ['name' => 'nominee_relationship.create'],
            ['name' => 'nominee_relationship.edit'],
            ['name' => 'nominee_relationship.delete'],
            
            ['name' => 'preferred_rto.list'],
            ['name' => 'preferred_rto.show'],
            ['name' => 'preferred_rto.create'],
            ['name' => 'preferred_rto.edit'],
            ['name' => 'preferred_rto.delete'],
            
            ['name' => 'manufacturer.list'],
            ['name' => 'manufacturer.show'],
            ['name' => 'manufacturer.create'],
            ['name' => 'manufacturer.edit'],
            ['name' => 'manufacturer.delete'],
            
            ['name' => 'gender_mapping.list'],
            ['name' => 'gender_mapping.show'],
            ['name' => 'gender_mapping.create'],
            ['name' => 'gender_mapping.edit'],
            ['name' => 'gender_mapping.delete'],
            
            ['name' => 'third_party_settings.list'],
            ['name' => 'third_party_settings.show'],
            ['name' => 'third_party_settings.create'],
            ['name' => 'third_party_settings.edit'],
            ['name' => 'third_party_settings.delete'],
            
            ['name' => 'template_master.list'],
            ['name' => 'template_master.show'],
            ['name' => 'template_master.create'],
            ['name' => 'template_master.edit'],
            ['name' => 'template_master.delete'],
            
            ['name' => 'query_builder.list'],
            ['name' => 'query_builder.show'],
            ['name' => 'query_builder.create'],
            ['name' => 'query_builder.edit'],
            ['name' => 'query_builder.delete'],
            
            ['name' => 'ic_configurator.list'],
            ['name' => 'ic_configurator.show'],
            ['name' => 'ic_configurator.create'],
            ['name' => 'ic_configurator.edit'],
            ['name' => 'ic_configurator.delete'],
            
            ['name' => 'user_activity_logs.list'],
            ['name' => 'user_activity_logs.show'],
            ['name' => 'user_activity_logs.create'],
            ['name' => 'user_activity_logs.edit'],
            ['name' => 'user_activity_logs.delete']
        ];
        foreach ($permissions as $key => $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate($permission);
        }
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
        \App\Models\User::first()->assignRole($role);

        $this->call([
        	LogsTableSeeder::class,
    	]);
    }
}
