@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.role.index') }}" class="btn btn-dark mb-4"><i class="fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Roles List</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.role.store') }}" method="POST">@csrf
            <div class="form-group row">
                <label for="name" class="col-sm-2 col-form-label required">Role Name</label>
                <div class="col-sm-4">
                    <input type="text" name="name" class="form-control" id="name" placeholder="Role Name" value="{{ old('name') }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
            </div>
            <br>
            <h5 class="mb-3">Permissions:</h5>
            <hr>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Admin User</label>
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.list">
                                View User List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.show">
                                Show User
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.create">
                                Create User
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.edit">
                                Edit User
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.delete">
                                Delete User
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all"  name="permission[]" value="password_policy">
                                Password Policy
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.list">
                                View Role List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.show">
                                Show Role
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.create">
                                Create Role
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.edit">
                                Edit Role
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="role.delete">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.list">
                                View Log List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.show">
                                Show Log
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.server-log">
                                Server Log
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.vahan-service">
                                Vahan Service Log
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="datapushlog.list">
                                Data Push Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.renewal-api">
                                Renewal Api Log
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.user-activity">
                                User Activity Log
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="journey_data.list">
                                Journey Data
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="log.request_response_tryit_option">
                                Request-Response Try-it Option
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal-data-migration.report">
                                Renewal Data Migration Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_log.list">
                                Ckyc Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_wrapper_log.list">
                                Ckyc Wrapper Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.list">
                                Journey Stage Count
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="kafka_log.list">
                                Kafka Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="kafka_log.list">
                                Kafka Sync Statistics
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_paty_payment.list">
                                Third Party Payment Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="push_api.list">
                                Push Api Data
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="icici_master.list">
                                Icici Master Download
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="error_master.list">
                                Error List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="trace_journey_id.list">
                                Get Trace ID
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_party_api.list">
                                Third Party Api Responses
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mongodb.list">
                                Dashboard Mongo Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="onepay_log.list">
                                Onepay Transaction Log
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ongrid_fastlane.list">
                                OLA Ongrid Logs
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ola_whatsapp_log.list">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.list">
                                View Company List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.show">
                                Show Company
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.edit">
                                Edit Company
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="company.delete">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.list">
                                View Configuration List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.show">
                                Show Configuration
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.create">
                                Create Configuration
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.edit">
                                Edit Configuration
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configuration.delete">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.list">
                                View Master Policy List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.show">
                                Show Master Policy
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.create">
                                Create Master Policy
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_policy.edit">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.list">
                                View Policy Wording List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.show">
                                Show Policy Wording
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.edit">
                                Edit Policy Wording
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="policy_wording.delete">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mmv_data.list">
                                Show MMV DATA
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="mmv_data.edit" >
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer.list">
                                Show Previous Insurer
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer.edit">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="manufacturer.list">
                                Show Manufacturer
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="manufacturer.edit">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.list">
                                View USP List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.show">
                                Show USP
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.create">
                                Create USP
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.edit">
                                Edit USP
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="usp.delete">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.list">
                                View Report List
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.show">
                                Show Report
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report_policy_upload.edit">
                                Policy Upload
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="report.broker_name.show">
                                Show Broker Name Report
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="CashlessGarage.list">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="discount.config">
                                Discount Configuration
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="discount.config.activity-logs">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user-journey-activities.clear">
                                Clear Activities
                            </label>
                        </div>
                    </div>
                </div>
            </div>

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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal-data-migration.report">
                                List Data
                            </label>
                        </div>
                    </div>
                </div>
            </div> --}}
            <hr>
            <div class="row">
                <div class="col-md-3">
                    <label for="">Master Configurator</label>
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.field">
                                Field Config
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.onboarding">
                                OnBoarding Config
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.proposal">
                                Proposal Validation
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="configurator.OTP">
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master.sync.fetch">Fetch All Masters
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master.sync.logs">Sync Logs
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal_data_upload.view" >Upload Offline Renewal Data
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="renewal_data_upload.sync" >Run Renewal Data Migration Job
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="sql_runner.view">Permission to run SQL
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="pos.list">
                                Permission to Download Pos data
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="encrypt-decrypt.list">
                                Permission for Encryption/Decryption
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="payment.list">
                                Permission for Payment Response
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="rto_master.list">
                                Permission for RTO Master
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ic-error-handling.list">
                                Permission for IC Error Handler
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_occupation.list">
                                Permission for Master Occuption
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="master_occuption_name.list">
                                Permission for Master Occuption Name
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="third_party.list">
                                Permission for Third Party Settings
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="previous_insurer_mappping.list">
                                Permission for Previous Insurer Mappping
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="preferred_rto.list">
                                Permission for Preferred RTO
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="addon_configuration.list">
                                Permission for Addon Configuration
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="financing_agreement.list">
                                Permission for Financing agreement
                            </label>
                        </div>
                            <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="nominee_relationship.list">
                                Permission for Nominee relationship
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="gender_mapping.list">
                                Permission for Gender Mapping
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_mg_data.list">
                                Permission for ABIBL MG DATA Migration
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_old_data.list">
                                Permission for ABIBL OLD DATA Migration
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="abibl_hyundai_data.list">
                                Permission for  Hyundai Data Upload
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="user.list">
                                Permission for Gramcover POS Data Sync
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ckyc_not_a_failure_cases.list">
                                Ckyc Not A Failure Cases
                            </label>
                        </div>
                    </div>
                </div>
            </div>
                <hr>
                <div class="row">
                    <div class="col-md-3">
                        <label for=""> IC Configuration</label>
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
                                    <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ic_configurator.view">
                                    IC Configurator
                                </label>
                            </div>
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input check-all" name="permission[]" value="ic_configurator.credentials.editable">
                                    IC Configurator Credentials Editable
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            <hr>
            <button type="submit" class="btn btn-primary mb-2">Submit</button>
        </form>
    </div>
</div>
@endsection('content')
@section('scripts')
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
@endsection