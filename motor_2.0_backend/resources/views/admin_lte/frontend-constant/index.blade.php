@extends('admin_lte.layout.app', ['activePage' => 'Vahan', 'titlePage' => __('Vahan')])
@section('content')
<style>
    .styled-table {
    border-collapse: collapse;
    margin: 25px 0;
    font-size: 0.9em;
    font-family: sans-serif;
    min-width: 400px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
}

.styled-table thead tr {
    /* background-color: #002998; */
    color: #ffffff;
    text-align: center;
}

.styled-table th,
.styled-table td {
    padding: 12px 15px;
}

.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
}

.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}

.styled-table tbody tr:last-of-type {
    border-bottom: 2px solid #009879;
}

.styled-table tbody tr.active-row {
    font-weight: bold;
    color: #009879;
}

.styled-table {
    word-wrap: break-word;
    table-layout: fixed;
    width: 100%;
}

input[type="text"] {
    width: 100%;
    height: 30px;
    font-size: 14pt;
    box-sizing: border-box;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
}

</style>
    <!-- <div class="content-wrapper"> -->
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        {{-- <form action="{{ route('admin.vahan_credentials_read.crud', $id) }}" method="get">
                            {{ csrf_field() }}
                            <h5 class="card-title">{{ $service }}
                            </h5>
                        </form> --}}
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div id="wrapper" class="page">
                            <table class="styled-table" align='center' cellspacing=2 cellpadding=1 id="data_table" border=1>
                                <thead class="p-3 mb-2 bg-primary text-white">
                                <tr>
                                    <th>Key</th>
                                    <th>Datatype</th>
                                    <th>Value</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" class="form-control" id="new_key" required></td>
                                        <td>
                                            <select id="new_datatype"  class="form-select" aria-label="Default select example">
                                                <option selected>select menu</option>
                                                <option value="Int">Int</option>
                                                <option value="text">text</option>
                                                <option value="boolean">boolean</option>
                                                <option value="null">null</option>
                                            </select>
                                        </td>
                                        <td align="center"><input type="text" onblur="keyCheck()" class="form-control" id="new_value" required></td>
                                        @can('frontend-constant.create')
                                        <td align="center"><a class="btn btn-primary btn-sm"
                                            class="add" onclick="add_row();" value="Add Row"><i class="fa fa-plus"
                                                    aria-hidden="true"></i></a></td>
                                                    @endcan()
                                    </tr>
                                @foreach ($data as $key => $const)
                                    <tr id={{ 'row'.$const->id }} align='center'>
                                        <td id={{ 'key_row'.$const->id }} required>{{ $const->key ?? '' }}</td>
                                        <td id={{ 'datatype_row'.$const->id }} required>{{ $const->datatype ?? '' }}</td>
                                        <td id={{ 'value_row'.$const->id }} required>{{ $const->value ?? '' }}</td>
                                        <td align='center'>
                                            @can('frontend-constant.edit')
                                            <a class="btn btn-outline-success btn-sm"
                                            title='Edit' id={{ 'edit_button'.$const->id }} value="Edit" class="edit"
                                            onclick="edit_row('{{ $const->id }}')"><i class="fa fa-edit"
                                                    aria-hidden="true"></i></a>
                                                    @endcan
                                                @can('frontend-constant.show')
                                                <a class="btn btn-outline-warning btn-sm"
                                                title='save' id={{ 'save_button'.$const->id }} value="Save" class="save"
                                                onclick="save_row('{{ $const->id }}')"><i class="fa fa-save"
                                                        aria-hidden="true"></i></a>
                                                        @endcan
                                                @can('frontend-constant.delete')
                                                <a class="btn btn-outline-danger btn-sm"
                                                title='Delete' value="Delete" class="delete" onclick="delete_row('{{ $const->id }}')"><i class="fa fa-trash"
                                                        aria-hidden="true"></i></a>
                                                        @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                <tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <!-- </div> -->
@endsection
@push('scripts_lte')
    <script>
        function keyCheck() {
            var key_value = document.getElementById("new_key").value;
            var new_value = document.getElementById("new_value").value;
            let url = "{{ route('admin.frontend_check') }}";
            $.ajax({
                type: "post",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "key_value": key_value,
                    "value": new_value,
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status == true) {
                        document.getElementById("new_key").value = '';
                        document.getElementById("new_value").value = '';
                        alert('key already exist');

                    }
                }
            });
        }

        function edit_row(no) {
            document.getElementById("edit_button" + no).style.display = "none";
            var key = document.getElementById("key_row" + no);
            var key_data = key.innerHTML;
            key.innerHTML = "<input type='text' id='key_text" + no + "' value='" + key_data + "'>";
            //
            var label = document.getElementById("datatype_row" + no);
            var label_data = label.innerHTML;
            label.innerHTML = "<select id='new1_datatype " + no + "'  class='form-select edit'>" +
                                    '<option selected>select menu</option>' +
                                    '<option value="Int" ' + (label_data  == 'Int' ? "selected" : '')+'>Int</option>' +
                                    '<option value="text" ' + (label_data  == 'text' ? "selected" : '')+'>text</option>' +
                                    '<option value="boolean"  ' + (label_data  == 'boolean' ? "selected" : '')+'>boolean</option>' +
                                    '<option value="null" ' + (label_data  == 'null' ? "selected" : '')+'>null</option>' +
                                "</select>";
            //
            var value = document.getElementById("value_row" + no);
            var value_data = value.innerHTML;
            value.innerHTML = "<input type='text' id='value_text" + no + "' value='" + value_data + "'>";
                switch(label_data){
                    case 'Int':
                        $('#'+'value_text' + no).attr('type','number');
                        $('#'+'value_text' + no).addClass('form-control');
                        break;
                    case 'text':
                        $('#'+'value_text' + no).attr('type','text');
                        $('#'+'value_text' + no).addClass('form-control');

                        break;
                    case 'boolean':
                        $('#'+'value_text' + no).attr('type','checkbox');
                        if (value_data == 'true') {
                            $('#'+'value_text' + no).attr('checked','checked');
                        } else {
                            // $('#'+'value_text' + no).attr('checked','checked');
                        }
                        $('#'+'value_text' + no).removeClass('form-control');
                        break;
                }
            $(".edit").change(function() {
            var data =  $(".edit" ).find(":selected").val();
            switch(data){
                case 'Int':
                    $('#'+'value_text' + no).attr('type','number');
                    // $('#'+'value_text' + no).val("");
                    $('#'+'value_text' + no).addClass('form-control');
                    $('#'+'value_text' + no).removeAttr('disabled');
                    break;
                case 'text':
                    $('#'+'value_text' + no).attr('type','text');
                    // $('#'+'value_text' + no).val("");
                    $('#'+'value_text' + no).addClass('form-control');
                    $('#'+'value_text' + no).removeAttr('disabled');
                    break;
                case 'boolean':
                    $('#'+'value_text' + no).removeClass('form-control');
                    // $('#'+'value_text' + no).val("");
                    $('#'+'value_text' + no).attr('type','checkbox');
                    $('#'+'value_text' + no).removeAttr('disabled');
                    if (value_data == true) {
                    $('#'+'value_text' + no).attr('checked');
                    }
                    $('#new_value:checkbox').change(function () {
                        if ($('#'+'value_text' + no).is(':checked') == true) {
                            $('#'+'value_text' + no).val('true');
                        }
                        else{
                            $('#'+'value_text' + no).val('false');
                        }
                    });
                    break;
                case 'null':
                    $('#'+'value_text' + no).attr('type','text');
                    $('#'+'value_text' + no).val("");
                    $('#'+'value_text' + no).addClass('form-control');
                    $('#'+'value_text' + no).attr('disabled','disabled');
                    break;
            }
        });
    }
        function save_row(no) {
                var key_val = document.getElementById("key_text" + no).value;
                var label_val = document.getElementById("new1_datatype " + no ).value;
                var value_val = document.getElementById("value_text" + no).value;
                let url ="{{ route('admin.frontend_update') }}";
                if (label_val == ''  || !label_val.trim().length || key_val == ''  || !key_val.trim().length || value_val == '' || !value_val.trim().length) {
                alert('Fill all fields');
                        } else {
            $.ajax({
                type: "post",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id" : no,
                    "label": label_val,
                    "key": key_val,
                    "val": value_val,
                },
                success: function(response) {
                    if (response.status == true) {
                        alert('Updated');
                            document.getElementById("edit_button" + no).style.display =
                                "block";
                            document.getElementById("save_button" + no).style.display =
                                "none";
                        location.reload();
                                    } else if(response.status == false){
                        alert('Something went wrong...!');
                    }else{
                        document.getElementById("key_text" + no).value ='';
                        alert('Key already exist!');
                    }
                }
            });
        }
                }

        function delete_row(no) {
            let url = "{{ route('admin.frontend_delete') }}";
            $.ajax({
                type: "delete",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id": no,
                },
                success: function(response) {
                    if (response.status == true) {
                        location.reload();
                        alert('Deleted successfully');

                    }
                }
            });
        }

        function add_row() {
            var new_key = document.getElementById("new_key").value;
            var new_value = document.getElementById("new_value").value;
            var new_datatype = document.getElementById("new_datatype").value;
                        if (new_datatype == '' || !new_datatype.trim().length  || new_key == '' || !new_key.trim().length || (new_datatype == 'null') ? '' : new_value == '' || !new_value.trim().length) {
                alert('Fill the required fields');
                        } else {
                            let url2 ="{{ route('admin.frontend_store') }}";
            $.ajax({
                type: "post",
                url: url2,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "new_key": new_key,
                    "new_value": new_value,
                    "new_datatype": new_datatype,
                },
                success: function(response) {
                    if (response.status == true) {
                        location.reload();
                        alert('Added');
                    }
                    else if(response.status == false)
                    {
                        alert('Something went wrong...!');
                    }
                    else if(response.status == 'exist')
                    {
                        alert('Key already exist');
                    }else{
                        alert('Service is inactive');
                    }
                }
            });
        }
    }
    </script>
    <script>
        $("#new_value").attr('disabled','disabled');
        $('#new_datatype').change(function() {
            if($('#new_value').is(':checked')){
                $('#new_value').prop( "checked", false );
            }
            var data =  $('#new_datatype').find(":selected").val();
            switch(data){
                case 'Int':
                    $("#new_value").attr('type','number');
                    $('#new_value').val("");
                    $("#new_value").addClass('form-control');
                    $("#new_value").removeAttr('disabled');
                    break;
                case 'text':
                    $("#new_value").attr('type','text');
                    $('#new_value').val("");
                    $("#new_value").addClass('form-control');
                    $("#new_value").removeAttr('disabled');
                    break;
                case 'boolean':
                    $("#new_value").removeClass('form-control');
                    $('#new_value').val("");
                    $("#new_value").attr('type','checkbox');
                    $("#new_value").removeAttr('disabled');
                    if ($('#new_value').is(':checked') != 'true') {
                        $('#new_value').val('false');
                    }
                    $('#new_value:checkbox').change(function () {
                        if ($('#new_value').is(':checked') == true) {
                            $('#new_value').val('true');
                        }
                    });
                    break;
                case 'null':
                    $("#new_value").attr('type','text');
                    $('#new_value').val("");
                    $("#new_value").addClass('form-control');
                    $("#new_value").attr('disabled','disabled');
                    break;
                case 'select menu':
                $('#new_value').val("");
                $("#new_value").addClass('form-control');
                $("#new_value").attr('disabled','disabled');
                break;
            }
        });
    </script>
@endpush
