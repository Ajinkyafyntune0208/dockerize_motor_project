@extends('admin_lte.layout.app', ['activePage' => 'log_rotation', 'titlePage' => __('Log Rotation')])
@section('content')
    <div class="card">
        <div class="card-header">
            @if (session('class'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('message') }}
                </div>
            @endif
            <a href="{{ route('admin.log_rotation.create') }}" class="btn btn-primary float-right">Add +</a>
        </div>
        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Sr. No.</th>
                        <th>Log Type</th>
                        <th>Location</th>
                        <th>DB Table</th>
                        <th>Backup Data Onwards</th>
                        <th>Log Rotation Frequency</th>
                        <th>Log To Be Retained</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $srNo = 1;
                    @endphp
                    @foreach ($list as $data)
                        <tr>
                            <td>
                                <div class="btn-group btn-group-toggle">
                                    <a class="btn btn-info mr-1"
                                        href="{{ route('admin.log_rotation.edit', $data->log_rotation_id) }}"><i
                                            class="far fa-edit"></i></a>
                                    <form action="{{ route('admin.log_rotation.destroy', $data->log_rotation_id) }}"
                                        method="post" onsubmit="return confirm('Are you sure..?')">@csrf
                                        @method('delete')
                                        <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                            <td>{{ $srNo++ }}</td>
                            <td>{{ Str::title($data->type_of_log) }}</td>
                            <td>{{ $data->location }}</td>
                            <td>{{ $data->db_table }}</td>
                            <td>{{ $data->backup_data_onwards }} Day(s)</td>
                            <td>{{ Str::title($data->log_rotation_frequency) }}</td>
                            <td>{{ $data->log_to_be_retained }} Day(s)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(function() {
            $("#data-table").DataTable({
                "responsive": false,
                "lengthChange": true,
                "autoWidth": false,
                "scrollX": true,
                "buttons": [
                    "copy", 
                    "csv", 
                    "excel", 
                    {
                        extend: "pdf",
                        customize: function (doc) {
                            // Align all text in the table to center
                            doc.content[1].table.widths = ['10%', '10%', '30%', '20%', '20%', '10%'];
                            doc.styles.tableBodyEven.alignment = 'center';
                            doc.styles.tableBodyOdd.alignment = 'center';
                            doc.styles.tableHeader.alignment = 'center';
                            doc.styles.tableHeader.fontSize = 10;
                            doc.styles.tableBodyEven.fontSize = 9;
                            doc.styles.tableBodyOdd.fontSize = 9;
                        }
                    }, 
                    "print", 
                    {
                        extend: 'colvis',
                        columns: 'th:not(:nth-child(3))'
                    }
                ]
            }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
        });
    </script>
@endsection
