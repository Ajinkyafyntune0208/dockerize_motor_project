@extends('admin_lte.layout.app', ['activePage' => 'log_configurator', 'titlePage' => __('Log Configurator')])
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@section('content')
    <!-- general form elements disabled -->
    <a href="{{ route('admin.log_configurator.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
    <div class="card card-primary">
        <div class="card-body">
            <form action="{{ route('admin.log_configurator.update',$log->log_configurator_id) }}" method="POST">@csrf @method('PUT')
                <div class="row">
                    <!-- text input -->
                    {{-- <div class="col-sm-12">
                        <div class="form-group">
                            <label class="required">Application</label>
                            <select class="form-control select2" name="application">
                                <option value="">Show All</option>
                                <option value="Motor">Motor</option>
                                <option value="CKYC">CKYC</option>
                            </select>
                            @error('application')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div> --}}
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label class="required">Type of Log</label>
                            <select class="form-control" name="type_of_log" id="type_of_log">
                                <option value="">Show All</option>
                                <option value="File System" {{ $log->type_of_log == 'File System' ? 'selected' : ''}}>File System</option>
                                <option value="Database" {{ $log->type_of_log == 'Database' ? 'selected' : ''}}>Database</option>
                            </select>
                            @error('type_of_log')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-12" id="location_field">
                        <div class="form-group">
                            <label class="active required" for="location">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="Location" value="{{ $log->location_path }}">
                            @error('Location')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-12" id="database_table_field">
                        <div class="form-group" >
                            <label class="required" for="database_table">Database Table</label>
                            <select class="form-control" name="database_table">
                                <option value="">Show All</option>
                                <option value="quote_visibility_logs" {{ $log->database_table == 'quote_visibility_logs' ? 'selected' : ''}}>quote_visibility_logs</option>
                                <option value="whatsapp_request_responses" {{ $log->database_table == 'whatsapp_request_responses' ? 'selected' : ''}}>whatsapp_request_responses</option>
                                <option value="voluntary_deductible" {{ $log->database_table == 'voluntary_deductible' ? 'selected' : ''}}>voluntary_deductible</option>
                                <option value="mail_log" {{ $log->database_table == 'mail_log' ? 'selected' : ''}}>mail_log</option>
                                <option value="http_log" {{ $log->database_table == 'http_log' ? 'selected' : ''}}>http_log</option>
                            </select>
                            @error('option')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="form-group">
                            <label>Backup Data Onwards</label>
                            <select class="form-control" name="backup_bata_onward">
                                <option value="">Show All</option>
                                <option value="Last 15 Days" {{ $log->backup_bata_onward == 'Last 15 Days' ? 'selected' : ''}}>Last 15 Days</option>
                                <option value="Last 30 Days" {{ $log->backup_bata_onward == 'Last 30 Days' ? 'selected' : ''}}>Last 30 Days</option>
                                <option value="Last 180 Days" {{ $log->backup_bata_onward == 'Last 180 Days' ? 'selected' : ''}}>Last 180 Days</option>
                                <option value="Last 365 Days" {{ $log->backup_bata_onward == 'Last 365 Days' ? 'selected' : ''}}>Last 365 Days</option>

                            </select>
                            @error('backup_bata_onward')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label>Log Rotation Frequency</label>
                            <select class="form-control" name="log_rotation_frequency">
                                <option value="">Show All</option>
                                <option value="Daily" {{ $log->log_rotation_frequency == 'Daily' ? 'selected' : ''}}>Daily</option>
                                <option value="weekly" {{ $log->log_rotation_frequency == 'weekly' ? 'selected' : ''}}>weekly</option>
                                <option value="monthly" {{ $log->log_rotation_frequency == 'monthly' ? 'selected' : ''}}>monthly</option>
                                <option value="yearly" {{ $log->log_rotation_frequency == 'yearly' ? 'selected' : ''}}>yearly</option>
                            </select>
                            @error('log_rotation_frequency')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label>Log to Retained</label>
                            <select class="form-control" name="log_to_retained" data-live-search="true">
                                <option value="Last 15 Days" {{ $log->log_to_retained == 'Last 15 Days' ? 'selected' : ''}}>Last 15 Days</option>
                                <option value="Last 30 Days" {{ $log->log_to_retained == 'Last 30 Days' ? 'selected' : ''}}>Last 30 Days</option>
                                <option value="Last 180 Days" {{ $log->log_to_retained == 'Last 180 Days' ? 'selected' : ''}}>Last 180 Days</option>
                                <option value="Last 365 Days" {{ $log->log_to_retained == 'Last 365 Days' ? 'selected' : ''}}>Last 365 Days</option>
                            </select>
                            @error('log_to_retained')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>

                    </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#database_table_field').hide();
            $('#location_field').hide();
            $('#database_table_select').select2({
                placeholder: "Select a table",
                allowClear: true
            });

            $('#type_of_log').change(function() {
                typeOfLog($(this).val());
            });
            typeOfLog($('#type_of_log').val());
            function typeOfLog(change) {
                if(change == 'Database') {
                    $('#database_table_field').show();
                    $('#location_field').hide();
                } else if(change == 'File System') {
                    $('#location_field').show();
                    $('#database_table_field').hide();
                } 
            }
        });
    </script>
@endsection
