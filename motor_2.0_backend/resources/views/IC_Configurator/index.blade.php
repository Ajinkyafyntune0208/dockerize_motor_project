@extends('layout.app', ['activePage' => 'IC_Configurator', 'titlePage' => __('IC Conf')])

@section('content')
    <script src="{{ asset('js/ic_conf/jquery.min.js') }}"></script>
    <style>
        /* Style the select dropdown */
        .form-group select {
            background-color: #FFFFFF;
            /* Set background color to white */
            border: 1px solid #CED4DA;
            /* Add a border */
            border-radius: 10px;
            /* Add border radius for rounded corners */
            padding: 6px 12px;
            /* Add padding */
            width: 100%;
            /* Set width to 100% */
            box-sizing: border-box;
            /* Include padding and border in width calculation */
        }

        .custom-select {
            border: 1px solid #ccc;
            /* Add a border */
            padding: 5px;
            /* Add padding for better appearance */
        }

        .table-cell {
            padding: 15px;
            border-right: 2px solid black;
            border-bottom: 2px solid black;
        }

        #configTable {
            border: 2px solid #000;
            border-radius: 20px;
            border-collapse: collapse;
            width: 100%;
            background-color: #f9f9f9;
        }

        #configTable th,
        #configTable td {
            padding: 5px;
            border-bottom: 2px solid #000;
            border-right: 2px solid #000;
        }

        #configTable th {
            background-color: #333;
            color: #fff;
        }

        #configTable tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        #configTable tbody tr:hover {
            background-color: #ddd;
        }

        #nextBtn {
            border-radius: 40px;
        }
    </style>
    <div class="content-wrapper">
        <div class="row px-1">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">IC Configurator</h5>
                </div>
            </div>
            <div class="col-md-6 mt-3 px-auto">
                {{-- Dropdown and Form --}}
                <center>
                    <div class="form-group">
                        <strong><label for="companySelect" class = "required">Company Alias</label></strong>
                        <select id="companySelect" class="selectpicker w-100 " data-live-search="true"
                            data-style="btn-sm btn-primary">
                            <option value="">Select Company Alias</option> <!-- Add an empty option -->
                            @foreach ($company as $companyItem)
                                <option value="{{ $companyItem->company_alias }}">{{ $companyItem->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </center>
            </div>
            <div class="col-md-6 mt-3 px-auto">
                <center>
                    <div class="form-group">
                        <strong><label class ="required">Section</label></strong>

                        <select name="=section" id="section" data-style="btn-sm btn-primary" class="selectpicker w-100"
                            data-live-search="true">
                            <option value="">Nothing selected</option>
                            <option value="Bike">Bike</option>
                            <option value="Car">Car</option>
                            <option value="CV">CV</option>
                        </select>
                    </div>
                </center>
            </div>
            <div class="col-md-2">
                <div class="d-flex align-items-center h-100">
                    <input type="submit" id='searchBtn' class="btn btn-primary   btn-sm w-100" value="Search">
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <form action="{{ url('admin/ic-config/add') }}" method="POST">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <div style="overflow-x: auto;">
                        <table id="configTable" class="table">
                            <thead>
                                <tr>
                                    <th class="bg-primary">#</th>
                                    <th class="bg-primary">Label</th>
                                    <th class="bg-primary">Key</th>
                                    <th class="bg-primary">Value</th>
                                    <th class="bg-primary">Default Value</th>
                                    <th class="bg-primary">Is Nullable</th>
                                </tr>
                            <div class="alert alert-danger" role="alert" id = "errorAlert" hidden></div>
                            </thead>
                            <tbody id="configData">
                                <!-- Config data will be filled dynamically using JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <center>
                            <!-- <button type="submit" class="btn btn-dark" onclick="handleSubmit()">Submit</button> -->
                            @can('ic_configurator.credentials.editable')
                                <button type="submit" id='submitBtn' hidden class="btn btn-primary">Submit</button>
                            @endcan
                        </center>
                    </div>
                    <div class="d-flex align-items-center justify-content-end">
                        <button type="button" id="nextBtn" class="btn btn-success">IC Product -></button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#searchBtn').click(async function() {
                var companyId = $('#companySelect').val();
                var section = $('#section').val();
                if (!companyId || !section) {
                    alert('Please select both company and section');
                    return; // Stop further execution
                }
                // if (companyId && section) {
                await $.ajax({
                    url: '{{ url('api/ic-config/listk') }}',
                    method: 'GET',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "company_alias": companyId,
                        "section": section
                    },
                    success: function(response) {
                        if(response.status == false){
                            $('#errorAlert').attr('hidden',false);
                            $('#errorAlert').text(response.msg)
                       }else{
                       $('#errorAlert').attr('hidden',true);
                       }
                        if (typeof response === 'object' && !Array.isArray(response)) {
                            $('#configData').empty();
                            var sr_no = 1;
                            // Iterate over the keys of the response object
                            if(response.data_flow == 'Y')
                            {
                                $('#configData').append(response.data).show();
                            }
                            else
                            {
                            Object.keys(response.data).forEach(function(key) {
                                var config = response.data[key];

                                if (typeof config === 'object' && !Array
                                    .isArray(config) && config.config_key !==
                                    null) {
                                    var configValue = config.value || '';
                                    var isChecked = configValue === '';

                                    $('#configData').append(
                                        '<tr style="border-bottom: 2px solid black;">' +
                                        '<td class="table-cell">' + (sr_no) +
                                        '</td>' +
                                        '<td class="table-cell">' + (config
                                            .config_name || '') +
                                        '</td><input type="hidden" name="config_name[]" value="' +
                                        config.config_name + '"/>' +
                                        '<td class="table-cell">' + (config
                                            .config_key || '') +
                                        '</td><input type="hidden" name="config_key[]" value="' +
                                        config.config_key + '"/>' +
                                        '</td>' +
                                        '<td class="table-cell"><input type="text" name="config_value[]" class="form-control" value="' +
                                        configValue +
                                        '" style="width: 400px;"></td>' +
                                        '<td class="table-cell">' +
                                        (config.default_value ?
                                            '<span hidden id="' + sr_no + '">' +
                                            (config.default_value || '') +
                                            '</span><i  class="mdi mdi-eye-off eye-icon" onclick="eyeButton(' +
                                            sr_no + ',event)"></i>' : '') +
                                        '</td><input type="hidden" name="default_value[]" value="' +
                                        config.default_value + '"/>' +
                                        '<td class="table-cell"><input type="checkbox" class="clear-checkbox" ' +
                                        (isChecked ? 'checked' : '') +
                                        '></td>' +
                                        '</tr>');
                                }
                                sr_no++;
                            });
                        }
                            if ($('#configData').html() != '') {
                                $('#submitBtn').removeAttr('hidden');
                            } else {
                                $('#submitBtn').attr('hidden', true)
                            }

                            // Event listener for checkbox change
                            $('.clear-checkbox').change(function() {
                                var inputField = $(this).closest('tr').find(
                                    'input[name="config_value[]"]');

                                // Check if the checkbox is checked
                                if ($(this).is(':checked')) {
                                    inputField.data('original-value', inputField
                                        .val());

                                    inputField.val('');
                                    inputField.prop('readonly', true);
                                } else {
                                    var originalValue = inputField.data(
                                        'original-value');

                                    inputField.val(originalValue);
                                    inputField.prop('readonly', false);
                                }
                            });
                        } else {
                            // Handle other response formats or unexpected data
                            console.error("Unexpected response format:", response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
                // }
            });
        });

        function handleSubmit() {
            $.ajax({
                url: '{{ url('/api/ic-config/add') }}',
                method: 'POST',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "config_name": $('#configData').find('input[name="config_name"]').val(),
                    "config_key": $('#configData').find('input[name="config_key"]').val(),
                    "config_value": $('#configData').find('input[name="config_value"]').val(),
                    "default_value": $('#configData').find('input[name="default_value"]').val()
                },

                success: function(response) {},
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
        };
        $('#nextBtn').click(function() {
            window.location.href = '{{ url('admin/ic-config/product_config') }}';
        });

        function eyeButton(id, event) {

            var iconElement = event.target;
            if (iconElement.classList.contains('mdi-eye-off')) {
                iconElement.classList.remove('mdi-eye-off');
                iconElement.classList.add('mdi-eye');
            } else {
                iconElement.classList.remove('mdi-eye');
                iconElement.classList.add('mdi-eye-off');
            }
            if (id) {
                var element = document.getElementById(id);
                element.hidden = element.hidden ? false : true;
            }
        };
    </script>
@endsection
