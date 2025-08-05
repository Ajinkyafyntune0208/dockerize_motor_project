@extends('layout.app', ['activePage' => 'vahan', 'titlePage' => __('vahan')])
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
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.vahan_credentials_read.crud', $id) }}" method="get">
                            {{ csrf_field() }}
                            <h5 class="card-title">{{ $service }}
                            </h5>
                        </form>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div id="wrapper" class="page">
                            <input type="hidden" id="v_id" value={{ $id }}>
                            <table class="styled-table" align='center' cellspacing=2 cellpadding=1 id="data_table" border=1>
                                <thead class="p-3 mb-2 bg-primary text-white">
                                     <tr>
                                    <th>Label</th>
                                    <th>Key</th>
                                    <th>Value</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($vahan_services as $key => $vahan_service)
                                    <tr id={{ 'row'.$vahan_service->id }} align='center'>
                                        <td id={{ 'label_row'.$vahan_service->id }} required>{{ $vahan_service->label ?? '' }}</td>
                                        <td id={{ 'key_row'.$vahan_service->id }} required>{{ $vahan_service->key ?? '' }}</td>
                                        <td id={{ 'value_row'.$vahan_service->id }} required>{{ $vahan_service->value ?? '' }}</td>
                                        <td align='center'>
                                            <a class="btn btn-outline-success btn-sm"
                                            title='Edit' id={{ 'edit_button'.$vahan_service->id }} value="Edit" class="edit"
                                            onclick="edit_row('{{ $vahan_service->id }}')"><i class="fa fa-pencil-square-o"
                                                    aria-hidden="true"></i></a>

                                                <a class="btn btn-outline-warning btn-sm"
                                                title='save' id={{ 'save_button'.$vahan_service->id }} value="Save" class="save"
                                                onclick="save_row('{{ $vahan_service->id }}')"><i class="fa fa-save"
                                                        aria-hidden="true"></i></a>

                                                <a class="btn btn-outline-danger btn-sm"
                                                title='Delete' value="Delete" class="delete" onclick="delete_row('{{ $vahan_service->id }}')"><i class="fa fa-trash-o"
                                                        aria-hidden="true"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                                
                                <tr>
                                    <td><input type="text" id="new_label" required></td>
                                    <td><input type="text" id="new_key" onblur="keyCheck('{{ $id }}')" required></td>
                                    <td><input type="text" id="new_value" required></td>
                                    <td align="center"><a class="btn btn-primary btn-sm" 
                                        class="add" onclick="add_row('{{ $id }}');" value="Add Row"><i class="fa fa-plus"
                                                aria-hidden="true"></i></a></td>
                                </tr>
                                <tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        function keyCheck(id) {
            var key_value = document.getElementById("new_key").value;
            let url = "{{ route('admin.cred_keyCheck.check') }}";
            $.ajax({
                type: "post",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "v_id": id,
                    "key_value": key_value,
                },
                dataType: 'json',
                success: function(response) {
                    console.log(response);
                    if (response.status == true) {
                        document.getElementById("new_key").value = '';
                        alert('key already exist');
                        
                    }
                }
            });
        }

        function edit_row(no) {
            document.getElementById("edit_button" + no).style.display = "none";
            //document.getElementById("save_button" + no).style.display = "block";
            var label = document.getElementById("label_row" + no);
            var label_data = label.innerHTML;
            label.innerHTML = "<input type='text' id='label_text" + no + "' value='" + label_data + "'>";
            //
            var key = document.getElementById("key_row" + no);
            var key_data = key.innerHTML;
            key.innerHTML = "<input type='text' id='key_text" + no + "' value='" + key_data + "'>";
            //
            var value = document.getElementById("value_row" + no);
            var value_data = value.innerHTML;
            value.innerHTML = "<input type='text' id='value_text" + no + "' value='" + value_data + "'>";
        }

        function save_row(no) {
                        var key_val = document.getElementById("key_text" + no).value;
                        var label_val = document.getElementById("label_text" + no).value;
                        var value_val = document.getElementById("value_text" + no).value;
                        var id = document.getElementById("v_id").value;
                        let url =
                            "{{ route('admin.cred_update.update') }}";
                        if (label_val == ''  || !label_val.trim().length || key_val == ''  || !key_val.trim().length || value_val == '' || !value_val.trim().length) {
                alert('Fill all fields');
                        } else {
            $.ajax({
                type: "post",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "v_id": id,
                    "id": no,
                    "label": label_val,
                    "key": key_val,
                    "val": value_val,
                },
                success: function(response) {
                    console.log(response);
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
            let url = "{{ route('admin.cred_delete.delete') }}";
            $.ajax({
                type: "delete",
                url: url,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id": no,
                },
                success: function(response) {
                    console.log(response);
                    if (response.status == true) {
                        location.reload();
                        alert('Credentials Inactivated');
                        
                    }
                }
            });
        }

        function add_row(id) {
            var new_label = document.getElementById("new_label").value;
            var new_key = document.getElementById("new_key").value;
            var new_value = document.getElementById("new_value").value;
                        if (new_label == '' || !new_label.trim().length  || new_key == '' || !new_key.trim().length || new_value == '' || !new_value.trim().length) {
                alert('Fill the required fields');
                        } else {
                            let url2 =
                                "{{ route('admin.cred_insert.insert') }}";
            $.ajax({
                type: "post",
                url: url2,
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id": id,
                    "new_label": new_label,
                    "new_key": new_key,
                    "new_value": new_value,
                },
                success: function(response) {
                    console.log(response);
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
@endpush
