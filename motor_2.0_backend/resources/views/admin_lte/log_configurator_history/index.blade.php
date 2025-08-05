@extends('admin_lte.layout.app', ['activePage' => 'log_configurator', 'titlePage' => __('Log Configurator')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            <a href="{{ route('admin.log_configurator.create') }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add
                </button>
            </a>
        </div>
    </div>
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Sr. No.</th>
                    <th>type of log</th>
                    <th>location</th>
                    <th>Database Table</th>
                    <th>Backup Onward</th>
                    <th>Log Rotation Frequency</th>
                    <th>Log To Retained</th>
                </tr>
            </thead>
            <tbody>
                @foreach($log as $key => $value)
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            <a class="btn btn-info mr-1" href="{{ route('admin.log_configurator.edit', $value->log_configurator_id) }}"><i class="far fa-edit"></i></a> 
                            <form action="{{ route('admin.log_configurator.destroy', $value) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->type_of_log}}</td>
                    <td>{{$value->location_path}}</td>
                    <td>{{$value->database_table}}</td>
                    <td>{{$value->backup_onward}}</td>
                    <td>{{$value->log_rotation_frequency}}</td>
                    <td>{{$value->log_to_retained}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection('content')
@section('scripts')
<script>
    $(function () {
    $("#data-table").DataTable({
        "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });
</script>
@endsection('scripts')