@extends('admin_lte.layout.app', ['activePage' => 'log_rotation', 'titlePage' => __('Log Rotation')])
@section('content')
    <div class="card">
        <div class="card-header">
            @if (session('class'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('message') }}
                </div>
            @endif
            <a href="{{ route('admin.log_rotation.index') }}" class="btn btn-dark mb-4"><i
                    class=" fa fa-solid fa-arrow-left"></i></i></a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.log_rotation.store') }}" method="POST">@csrf
                <div class="row">
                    <!-- text input -->
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label class="required">Log Type</label>
                            <select class="form-control select2" name="type_of_log" id='type_of_log' required>
                                <option value="">Select Log Type</option>
                                <option {{ old('location') === 'database' ? 'selected' : '' }} value="database">Database
                                </option>
                                <option disabled {{ old('location') === 'file' ? 'selected' : '' }} value="file">File</option>
                            </select>
                            @error('type_of_log')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-4" id="location_div">
                        <div class="form-group">
                            <label>File Location</label>
                            <input type="text" class="form-control" placeholder="File Path" id="location_txt"
                                name="location" value="{{ old('location') }}">
                            @error('location')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-4" id="db_tables_div">
                        <div class="form-group">
                            <label>DB Table</label>
                            <select class="form-control select2" name="db_table" id="db_table_dd">
                                <option value="">Select Table</option>
                                @foreach ($logTables as $data)
                                    <option {{ old('db_table') === $data->table_name ? 'selected' : '' }}
                                        value="{{ $data->table_name }}">{{ $data->table_name }}</option>
                                @endforeach
                            </select>
                            @error('db_table')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label class="required">Backup Data Onwards</label>
                            <input type="number" min="1" max="365" class="form-control"
                                name="backup_data_onwards" placeholder="Enter Number of Days"
                                value="{{ old('backup_data_onwards') }}" required>
                            @error('backup_data_onwards')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label class="required">Log Rotation Frequency</label>
                            <select class="form-control select2" name="log_rotation_frequency" required>
                                <option value="">Select Frequency</option>
                                <option {{ old('log_rotation_frequency') === 'daily' ? 'selected' : '' }} value="daily">
                                    Daily</option>
                                <option {{ old('log_rotation_frequency') === 'weekly' ? 'selected' : '' }} value="weekly">
                                    Weekly</option>
                                <option {{ old('log_rotation_frequency') === 'monthly' ? 'selected' : '' }}
                                    value="monthly">Monthly</option>
                                <option {{ old('log_rotation_frequency') === 'quaterly' ? 'selected' : '' }}
                                    value="quaterly">Quaterly</option>
                                <option {{ old('log_rotation_frequency') === 'yearly' ? 'selected' : '' }} value="yearly">
                                    Yearly</option>
                            </select>
                            @error('log_rotation_frequency')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label class="required">Log To Be Retained</label>
                            <input type="number" min="1" max="365" class="form-control"
                                name="log_to_be_retained" placeholder="Enter Number of Days"
                                value="{{ old('log_to_be_retained') }}" required>
                            @error('log_to_be_retained')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-12 text-right">
                        <div class="d-flex justify-content-center">
                            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                        </div>
                    </div>
            </form>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $('#location_div').hide()
        $('#db_tables_div').hide()
        $('#type_of_log').change(function() {
            let log_type = $('#type_of_log').val();
            if (log_type == 'database') {
                $('#location_div').hide()
                $('#location_txt').val("")
                $('#db_tables_div').show()
            } else if (log_type == 'file') {
                $('#location_div').show()
                $('#db_tables_div').hide()
                $('#db_table_dd').val("")
            } else {
                $('#location_div').hide()
                $('#db_tables_div').hide()
            }
        })
    </script>
@endsection
