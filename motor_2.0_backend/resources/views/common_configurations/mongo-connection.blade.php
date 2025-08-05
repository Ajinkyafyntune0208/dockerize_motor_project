<div class="card" style="margin-top: 1%;">
    <div class="card-body">
        @if (session('mongo-config-msg'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('mongo-config-msg') }}
            </div>
        @endif
        <h5 class="card-title m-3">MongoDB Status</h5>
        <div style="padding: 0% 5%" class="form-group">
            <form action="{{ route('admin.common-config-mongo') }}" method="POST">@csrf
                <div class="row mb-1">
                    <div class="col-md-3">
                        <label for="is_configured"><strong>Is MongoDB Configured ?</strong></label>
                    </div>
                    <div class="col-md-3">
                        <input type="checkbox" name="is_configured" id="is_configured"
                            {{ $existingRecord==='Y' ? 'checked' : '' }}>
                    </div>
                </div>
                <div id="config-form">
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_host">MONGO_DB_HOST</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_host" id="mongo_db_host" value="{{ isset($keyValuePairs['MONGO_DB_HOST']) ? $keyValuePairs['MONGO_DB_HOST'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_port">MONGO_DB_PORT</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_port" id="mongo_db_port" value="{{ isset($keyValuePairs['MONGO_DB_PORT']) ? $keyValuePairs['MONGO_DB_PORT'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_database">MONGO_DB_DATABASE</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_database" id="mongo_db_database" value="{{ isset($keyValuePairs['MONGO_DB_DATABASE']) ? $keyValuePairs['MONGO_DB_DATABASE'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_username">MONGO_DB_USERNAME</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_username" id="mongo_db_username" value="{{ isset($keyValuePairs['MONGO_DB_USERNAME']) ? $keyValuePairs['MONGO_DB_USERNAME'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_password">MONGO_DB_PASSWORD</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_password" id="mongo_db_password" value="{{ isset($keyValuePairs['MONGO_DB_PASSWORD']) ? $keyValuePairs['MONGO_DB_PASSWORD'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_authentication_database">MONGO_DB_AUTHENTICATION_DATABASE</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_authentication_database"
                                id="mongo_db_authentication_database" value="{{ isset($keyValuePairs['MONGO_DB_AUTHENTICATION_DATABASE']) ? $keyValuePairs['MONGO_DB_AUTHENTICATION_DATABASE'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_database">MONGO_DB_DATABASE</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_database" id="mongo_db_database" value="{{ isset($keyValuePairs['MONGO_DB_DATABASE']) ? $keyValuePairs['MONGO_DB_DATABASE'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_retry_writes">MONGO_DB_RETRY_WRITES</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_retry_writes"
                                id="mongo_db_retry_writes"  value="{{ isset($keyValuePairs['MONGO_DB_RETRY_WRITES']) ? $keyValuePairs['MONGO_DB_RETRY_WRITES'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_ssl_connection">MONGO_DB_SSL_CONNECTION</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_ssl_connection"
                                id="mongo_db_ssl_connection" value="{{ isset($keyValuePairs['MONGO_DB_SSL_CONNECTION']) ? $keyValuePairs['MONGO_DB_SSL_CONNECTION'] : '' }}">
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-3">
                            <label for="mongo_db_ca_file_path">MONGO_DB_CA_FILE_PATH</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="mongo_db_ca_file_path"
                                id="mongo_db_ca_file_path" value="{{ isset($keyValuePairs['MONGO_DB_CA_FILE_PATH']) ? $keyValuePairs['MONGO_DB_CA_FILE_PATH'] : '' }}">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-outline-primary">Submit</button>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    function fillValue($key, $keyValuePairs) {
    return isset($keyValuePairs[$key]) ? $keyValuePairs[$key] : '';
}
    function handleCheckBoxInputs() {
        if ($('#is_configured').is(':checked')) {
            $('#config-form').prop('hidden', false);
            $("#config-form input").attr('disabled', false);
        } else {
            $('#config-form').prop('hidden', true);
            $("#config-form input").attr('disabled', true);
        }
    }
    $(document).ready(function() {
        let focus = '{{ session('focus') }}';
        if (focus) {
            $('#is_configured').focus()
        }
        handleCheckBoxInputs();
        $('#is_configured').change(function() {
            handleCheckBoxInputs();
        })
    });
</script>
