@extends('layout.app', ['activePage' => 'role', 'titlePage' => __('Roles List')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Roles List</h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <form action="{{ route('admin.role.update', $role) }}" method="POST">@csrf @method('PUT')

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="form-group row">
                            <label for="name" class="col-sm-2 col-form-label required">Role Name</label>
                            <div class="col-sm-4">
                                <input type="text" name="name" class="form-control" id="name" placeholder="Role Name" value="{{ old('name', $role->name) }}" required>
                                @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <h5 class="card-title mb-3">Permissions:</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Admin User</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.list" {{ in_array('user.list', $permissions) ? 'checked' : '' }}>
                                            View User List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.show" {{ in_array('user.show', $permissions) ? 'checked' : '' }}>
                                            Show User
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.create" {{ in_array('user.create', $permissions) ? 'checked' : '' }}>
                                            Create User
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.edit" {{ in_array('user.edit', $permissions) ? 'checked' : '' }}>
                                            Edit User
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.delete" {{ in_array('user.delete', $permissions) ? 'checked' : '' }}>
                                            Delete User
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="password_policy" {{ in_array('password_policy', $permissions) ? 'checked' : '' }}>
                                            Password Policy
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="pos.agents" {{ in_array('pos.agents', $permissions) ? 'checked' : '' }}>
                                            POS Agents
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="frontend_constants.list" {{ in_array('frontend_constants.list', $permissions) ? 'checked' : '' }}>
                                            Frontend Constants
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Role And Permissions</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.list" {{ in_array('role.list', $permissions) ? 'checked' : '' }}>
                                            View Role List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.show" {{ in_array('role.show', $permissions) ? 'checked' : '' }}>
                                            Show Role
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.create" {{ in_array('role.create', $permissions) ? 'checked' : '' }}>
                                            Create Role
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.edit" {{ in_array('role.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Role
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.delete" {{ in_array('role.delete', $permissions) ? 'checked' : '' }}>
                                            Delete Role
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Logs</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-success">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.list" {{ in_array('log.list', $permissions) ? 'checked' : '' }}>
                                            View Log List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.show" {{ in_array('log.show', $permissions) ? 'checked' : '' }}>
                                            Show Log
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.server-log" {{ in_array('log.server-log', $permissions) ? 'checked' : '' }}>
                                            Server Log
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.renewal-api" {{ in_array('log.renewal-api', $permissions) ? 'checked' : '' }}>
                                            Renewal Api Log
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.user-activity" {{ in_array('log.user-activity', $permissions) ? 'checked' : '' }}>
                                            User Activity Log
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.vahan-service" {{ in_array('log.vahan-service', $permissions) ? 'checked' : '' }}>
                                            Vahan Service Log
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="datapushlog.list" {{ in_array('datapushlog.list', $permissions) ? 'checked' : '' }}>
                                            Data Push Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="journey_data.list" {{ in_array('journey_data.list', $permissions) ? 'checked' : '' }}>
                                            Journey Data
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.request_response_tryit_option" {{ in_array('log.request_response_tryit_option', $permissions) ? 'checked' : '' }}>
                                            Request-Response Try-it Option
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal-data-migration.report" {{ in_array('renewal-data-migration.report', $permissions) ? 'checked' : '' }}>
                                            Renewal Data Migration Logs
                                        </label>
                                    </div>
                                </div>                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_log.list" {{ in_array('ckyc_log.list', $permissions) ? 'checked' : '' }}>
                                            Ckyc Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_wrapper_log.list" {{ in_array('ckyc_wrapper_log.list', $permissions) ? 'checked' : '' }}>
                                            Ckyc Wrapper Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.list" {{ in_array('report.list', $permissions) ? 'checked' : '' }}>
                                            Journey Stage Count
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="kafka_log.list" {{ in_array('kafka_log.list', $permissions) ? 'checked' : '' }}>
                                            Kafka Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="kafka_log.list" {{ in_array('kafka_log.list', $permissions) ? 'checked' : '' }}>
                                            Kafka Sync Statistics
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_paty_payment.list" {{ in_array('third_paty_payment.list', $permissions) ? 'checked' : '' }}>
                                            Third Party Payment Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="push_api.list" {{ in_array('push_api.list', $permissions) ? 'checked' : '' }}>
                                            Push Api Data
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="icici_master.list" {{ in_array('icici_master.list', $permissions) ? 'checked' : '' }}>
                                            Icici Master Download
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="error_master.list" {{ in_array('error_master.list', $permissions) ? 'checked' : '' }}>
                                            Error List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="trace_journey_id.list" {{ in_array('trace_journey_id.list', $permissions) ? 'checked' : '' }}>
                                            Get Trace ID
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_party_api.list" {{ in_array('third_party_api.list', $permissions) ? 'checked' : '' }}>
                                            Third Party Api Responses
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mongodb.list" {{ in_array('mongodb.list', $permissions) ? 'checked' : '' }}>
                                            Dashboard Mongo Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="onepay_log.list" {{ in_array('onepay_log.list', $permissions) ? 'checked' : '' }}>
                                            Onepay Transaction Log
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ongrid_fastlane.list" {{ in_array('ongrid_fastlane.list', $permissions) ? 'checked' : '' }}>
                                            OLA Ongrid Logs
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ola_whatsapp_log.list" {{ in_array('ola_whatsapp_log.list', $permissions) ? 'checked' : '' }}>
                                            Ola Whatsapp Logs
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.create">
                                            Create Log
                                        </label>
                                    </div>
                                </div> -->
                                <!-- <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.edit">
                                            Edit Log
                                        </label>
                                    </div>
                                </div> -->
                                <!-- <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.delete">
                                            Delete Log
                                        </label>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Company</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.list" {{ in_array('company.list', $permissions) ? 'checked' : '' }}>
                                            View Company List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.show" {{ in_array('company.show', $permissions) ? 'checked' : '' }}>
                                            Show Company
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.edit" {{ in_array('company.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Company
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.delete" {{ in_array('company.delete', $permissions) ? 'checked' : '' }}>
                                            Delete Company
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Configuration</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.list" {{ in_array('configuration.list', $permissions) ? 'checked' : '' }}>
                                            View Configuration List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.show" {{ in_array('configuration.show', $permissions) ? 'checked' : '' }}>
                                            Show Configuration
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.create" {{ in_array('configuration.create', $permissions) ? 'checked' : '' }}>
                                            Create Configuration
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.edit" {{ in_array('configuration.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Configuration
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.delete" {{ in_array('configuration.delete', $permissions) ? 'checked' : '' }}>
                                            Delete Configuration
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Master Policy</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.list" {{ in_array('master_policy.list', $permissions) ? 'checked' : '' }}>
                                            View Master Policy List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.show" {{ in_array('master_policy.show', $permissions) ? 'checked' : '' }}>
                                            Show Master Policy
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.create" {{ in_array('master_policy.create', $permissions) ? 'checked' : '' }}>
                                            Create Master Policy
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.edit" {{ in_array('master_policy.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Master Policy
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Policy Wording</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.list" {{ in_array('policy_wording.list', $permissions) ? 'checked' : '' }}>
                                            View Policy Wording List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.show" {{ in_array('policy_wording.show', $permissions) ? 'checked' : '' }}>
                                            Show Policy Wording
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.edit" {{ in_array('policy_wording.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Policy Wording
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.delete" {{ in_array('policy_wording.delete', $permissions) ? 'checked' : '' }}>
                                            Delete Policy Wording
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">MMV DATA</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">

                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mmv_data.list" {{ in_array('mmv_data.list', $permissions) ? 'checked' : '' }}>
                                            Show MMV DATA
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mmv_data.edit" {{ in_array('mmv_data.edit', $permissions) ? 'checked' : '' }}>
                                            EDIT MMV DATA
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Previous Insurer</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer.list" {{ in_array('previous_insurer.list', $permissions) ? 'checked' : '' }}>
                                            Show Previous Insurer
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer.edit" {{ in_array('previous_insurer.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Previous Insurer
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Manufacturer</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="manufacturer.list" {{ in_array('manufacturer.list', $permissions) ? 'checked' : '' }}>
                                            Show Manufacturer
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="manufacturer.edit" {{ in_array('manufacturer.edit', $permissions) ? 'checked' : '' }}>
                                            Edit Manufacturer
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">USP</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.list" {{ in_array('usp.list', $permissions) ? 'checked' : '' }}>
                                            View USP List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.show" {{ in_array('usp.show', $permissions) ? 'checked' : '' }}>
                                            Show USP
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.create" {{ in_array('usp.create', $permissions) ? 'checked' : '' }}>
                                            Create USP
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.edit" {{ in_array('usp.edit', $permissions) ? 'checked' : '' }}>
                                            Edit USP
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.delete" {{ in_array('usp.delete', $permissions) ? 'checked' : '' }}>
                                            Delete USP
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Report</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.list" {{ in_array('report.list', $permissions) ? 'checked' : '' }}>
                                            View Report List
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.show" {{ in_array('report.show', $permissions) ? 'checked' : '' }}>
                                            Show Report
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report_policy_upload.edit" {{ in_array('report_policy_upload.edit', $permissions) ? 'checked' : '' }}>
                                            Policy Upload
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.broker_name.show" {{ in_array('report.broker_name.show', $permissions) ? 'checked' : '' }}>
                                            Show Broker Name Report
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="CashlessGarage.list" {{ in_array('CashlessGarage.list', $permissions) ? 'checked' : '' }}>
                                            Cashless Garage
                                        </label>
                                    </div>

                                </div>
                                
                            </div>
                        </div>

                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Agent Discount Configuration</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="discount.config" {{ in_array('discount.config', $permissions) ? 'checked' : '' }}>
                                            Discount Configuration
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="discount.config.activity-logs" {{ in_array('discount.config.activity-logs', $permissions) ? 'checked' : '' }}>
                                            Discount Configuration Activity Logs
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>User Journey Activities</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user-journey-activities.clear" {{ in_array('user-journey-activities.clear', $permissions) ? 'checked' : '' }}>
                                            Clear Activities
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        {{-- <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Renewal Data Migration</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal-data-migration.report" {{ in_array('renewal-data-migration.report', $permissions) ? 'checked' : '' }}>
                                            List Data
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr> --}}
                        <div class="row">
                            <div class="col-md-3">
                                <label>Master Configurator</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.field" {{ in_array('configurator.field', $permissions) ? 'checked' : '' }}>
                                            Field Config
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.onboarding" {{ in_array('configurator.onboarding', $permissions) ? 'checked' : '' }}>
                                            OnBoarding Config
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.proposal" {{ in_array('configurator.proposal', $permissions) ? 'checked' : '' }}>
                                           Proposal Validation
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.OTP" {{ in_array('configurator.OTP', $permissions) ? 'checked' : '' }}>
                                           OTP config
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Master Sync</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master.sync.fetch" {{ in_array('master.sync.fetch', $permissions) ? 'checked' : '' }}>Fetch All Masters
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master.sync.logs" {{ in_array('master.sync.logs', $permissions) ? 'checked' : '' }}>Sync Logs
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Offline Renewal Data Upload</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal_data_upload.view" {{ in_array('renewal_data_upload.view', $permissions) ? 'checked' : '' }}>Upload Offline Renewal Data
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal_data_upload.sync" {{ in_array('renewal_data_upload.sync', $permissions) ? 'checked' : '' }}>Run Renewal Data Migration Job
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label>Pocket SQL Runner</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-primary">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="sql_runner.view" {{ in_array('sql_runner.view', $permissions) ? 'checked' : '' }}>Permission to run SQL
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="">Miscellaneous menu</label>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <div class="form-check form-check-success">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input select-all">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="pos.list" {{ in_array('pos.list', $permissions) ? 'checked' : '' }}>
                                            Permission to Download Pos data
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="encrypt-decrypt.list"{{ in_array('encrypt-decrypt.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Encryption/Decryption
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="payment.list"{{ in_array('payment.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Payment Response
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="rto_master.list"{{ in_array('rto_master.list', $permissions) ? 'checked' : '' }}>
                                            Permission for RTO Master
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ic-error-handling.list"{{ in_array('ic-error-handling.list', $permissions) ? 'checked' : '' }}>
                                            Permission for IC Error Handler
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_occupation.list" {{ in_array('master_occupation.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Master Occuption
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_occuption_name.list" {{ in_array('master_occuption_name.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Master Occuption Name
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_party.list" {{ in_array('third_party.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Third Party Settings
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer_mappping.list" {{ in_array('previous_insurer_mappping.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Previous Insurer Mappping
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="preferred_rto.list" {{ in_array('preferred_rto.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Preferred RTO
                                        </label>
                                    </div>
                                     <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="addon_configuration.list" {{ in_array('addon_configuration.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Addon Configuration
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="financing_agreement.list" {{ in_array('financing_agreement.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Financing agreement
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="nominee_relationship.list" {{ in_array('nominee_relationship.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Nominee relationship
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="gender_mapping.list" {{ in_array('gender_mapping.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Gender Mapping
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_mg_data.list" {{ in_array('abibl_mg_data.list', $permissions) ? 'checked' : '' }}>
                                            Permission for ABIBL MG DATA Migration
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_old_data.list" {{ in_array('abibl_mg_data.list', $permissions) ? 'checked' : '' }}>
                                            Permission for ABIBL OLD DATA Migration
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_hyundai_data.list" {{ in_array('abibl_mg_data.list', $permissions) ? 'checked' : '' }}>
                                            Permission for  Hyundai Data Upload
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.list" {{ in_array('user.list', $permissions) ? 'checked' : '' }}>
                                            Permission for Gramcover POS Data Sync
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_not_a_failure_cases.list" {{ in_array('ckyc_not_a_failure_cases.list', $permissions) ? 'checked' : '' }}>
                                            Ckyc Not A Failure Cases
                                        </label>
                                    </div>

                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-3">
                                    <label>IC Configuration</label>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <div class="form-check form-check-success">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input select-all">
                                                Select All
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input check-all" name="permission[]"
                                                    value="ic_configurator.view" {{ in_array('ic_configurator.view', $permissions) ? 'checked' : ''}}>
                                                IC Configurator
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input check-all" name="permission[]"
                                                    value="ic_configurator.credentials.editable" {{ in_array('ic_configurator.credentials.editable',$permissions) ? 'checked' : '' }}>
                                                IC Configurator Credentials Editable
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input check-all" name="permission[]"
                                                    value="configurator.pos-imd" {{ in_array('configurator.pos-imd',$permissions) ? 'checked' : '' }}>
                                                Pos Imd Configurator
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input check-all" name="permission[]"
                                                    value="configurator.pos-imd" {{ in_array('configurator.payment_gateway',$permissions) ? 'checked' : '' }}>
                                                    Payment Gateway Configurator
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-primary mb-2">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        $('.select-all').click(function() {
            if ($(this).prop('checked')) {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', true);
            } else {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', false);
            }
        });
    });
</script>
@endpush
