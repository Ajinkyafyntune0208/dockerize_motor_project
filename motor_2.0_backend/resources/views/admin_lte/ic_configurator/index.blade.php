
@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('IC Configurator')])
@section('content')

    <section class="content">
        <div class="container-fluid">
            <div class="row">
            <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            @if (session('class'))
                                <div class="alert alert-{{ session('class') }}">
                                    {!! session('message') !!}
                                </div>
                            @endif
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <div class="row">
                                    <div class="col-md-5">
                                        <label for="companySelect">Company Alias <span class="text-danger">*</span></label>
                                        <select id="companySelect" class="border selectpicker w-100" data-live-search="true" data-style="btn btn-light">
                                            <option value="">Select Company Alias</option>
                                            @foreach ($company as $companyItem)
                                                <option value="{{ $companyItem->company_alias }}">{{ $companyItem->company_name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-center text-danger alias_error"></p>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="section">Section <span class="text-danger">*</span></label>
                                        <select name="section" id="section" data-style="btn btn-light" class="border selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing Selected</option>
                                            <option value="Bike">Bike</option>
                                            <option value="Car">Car</option>
                                            <option value="CV">CV</option>
                                        </select>
                                        <p class="text-center text-danger section_error"></p>
                                    </div>
                                    <div class="col-md-2" style="padding-top:33px;">
                                        <button type="submit" id='searchBtn' class="btn btn-info btn w-100">Search</button>
                                        <input type="hidden" id="user_authentication_status" value="{{ $user_details->authorization_status }}">
                                    </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>

                <div class="col-12">
                    <form action="{{ url('admin/ic-config/add') }}" method="POST" id="saveCredData">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="card">
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div id="load_table">

                                </div>
                                <!-- <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Label</th>
                                            <th>Key</th>
                                            <th>Value</th>
                                            <th>Default Value</th>
                                            <th>Is Nullable</th>
                                        </tr>
                                    </thead>
                                    <tbody id="configData">

                                    </tbody>
                                </table> -->

                                <hr>
                                <div class="form-group">
                                    <center>
                                        <textarea class="form-control" id="commentTextareaForm" name="commentTextareaForm" rows="3" placeholder="Enter your comment" hidden></textarea>
                                        <button type="button" id='submitBtn' hidden class="btn btn-dark">Submit</button>
                                    </center>
                                </div>
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" id="nextBtn" class="btn btn-success" style="border-radius: 40px;">IC Product <i class="fa fa-arrow-right ml-2"></i></button>
                                </div>

                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </form>
                </div>

                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>

    <div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentModalLabel">Add a Comment</h5>
                    <button type="button" class="close closebutton" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="commentTextarea" rows="3" placeholder="Enter your comment"></textarea>
                    <input type="hidden" id="actionType">
                    <input type="hidden" id="itemId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary closebutton" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="submitComment">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            
            if($('#user_authentication_status').val()=='Y'){
                $('#submitBtn').click(function() {
                    $('#commentModal').modal('show');
                });
                $('#submitComment').click(function() {
                    $('#commentTextareaForm').val($('#commentTextarea').val());
                    $('#saveCredData').submit();
                });
            }else{
                $('#submitBtn').click(function() {
                    $('#saveCredData').submit();
                });
            }

            $('#submitBtn').click(function() {
                $('#commentModal').modal('show');
            });

            $('.closebutton').click(function() {
                $('#commentModal').modal('hide');
            });

            $('#submitComment').click(function() {
                $('#commentTextareaForm').val($('#commentTextarea').val());
                $('#saveCredData').submit();
            });
            $('#searchBtn').click(async function() {
                var companyId = $('#companySelect').val();
                var section = $('#section').val();
                if (companyId =="") {
                    $(".section_error").html('');
                    $(".alias_error").html('Please select a company alias in the list.');
                    return false; // Stop further execution
                }
                else if (section =="") {
                    $(".alias_error").html('');
                    $(".section_error").html('Please select a section in the list.');
                    return false; // Stop further execution
                }
                await $.ajax({
                    url: listk,
                    method: 'GET',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "company_alias": companyId,
                        "section": section
                    },
                    success: function(response) {
                        if(response.status == false){
                            $('#errorAlert').attr('hidden',false);
                            $('#errorAlert').html(response.msg)
                            $("#load_table").html('');
                            $("#load_table").html('<p class="text-center text-danger">No data available in table</p>');
                            $('#submitBtn').attr('hidden', true);
                       }else{

                        var html =`<table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Label</th>
                                            <th>Key</th>
                                            <th>Value</th>
                                            <th>Default Value</th>
                                            <th>Is Nullable</th>
                                        </tr>
                                    </thead>
                                    <tbody id="configData">

                                    </tbody>
                                </table>`;

                        $("#load_table").html(html);
                        $('#errorAlert').attr('hidden',true);
                        $(".alias_error").html('');
                        $(".section_error").html('');

                        if (typeof response === 'object' && !Array.isArray(response)) {
                                $('#configData').html('');
                                var sr_no = 1;
                                // Iterate over the keys of the response object
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
                                            '<td class="table-cell" style="width: 200px;">' + (config
                                                .config_name || '') +
                                            '</td><input type="hidden" name="config_name[]" value="' +
                                            config.config_name + '"/>' +

                                            '<td class="table-cell" style="width: 200px;">' + (config
                                                .config_key || '') +
                                            '<input type="hidden" name="config_key[]" value="' +
                                            config.config_key + '"/>' +
                                            '</td>' +

                                            '<td class="table-cell"><input type="text" name="config_value[]" class="form-control" value="' +
                                            configValue +
                                            '" style="width: 200px;" '+(isChecked ? 'readonly' : '')+'></td>' +

                                            '<td class="table-cell">' +
                                            (config.default_value ?
                                                '<span hidden id="' + sr_no + '">' +
                                                (config.default_value || '') +
                                                '</span><i  class="fas fa-eye-slash eye-icon" onclick="eyeButton(' +
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


                                $(function () {
                                    $("#example1").DataTable({
                                    // "responsive": true, "lengthChange": false, "autoWidth": false,
                                        "buttons": ["copy", "csv", "excel", "pdf", "print",
                                                        {
                                                        extend: 'colvis',
                                                        columns: 'th:nth-child(n+3)'
                                                        }
                                                ],
                                                scrollCollapse: true,
                                                scrollX: true,
                                                scrollY: '700px',
                                                "bDestroy": true,
                                        }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
                                });

                                if ($('#configData').html() != '') {
                                    $('#submitBtn').removeAttr('hidden');
                                } else {
                                    $('#submitBtn').attr('hidden', true);
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

                       }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });
        });

        function handleSubmit() {
            $.ajax({
                url: add,
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
            window.location.href = 'product_config';
        });

        function eyeButton(id, event) {

            var iconElement = event.target;
            if (iconElement.classList.contains('fa-eye-slash')) {
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            } else {
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            }
            if (id) {
                var element = document.getElementById(id);
                element.hidden = element.hidden ? false : true;
            }
        };
    </script>

<script>
    const listk = "{{url('admin/ic-config/listk')}}";
    const add = "{{ url('admin/ic-config/add') }}";
 </script>
@endsection