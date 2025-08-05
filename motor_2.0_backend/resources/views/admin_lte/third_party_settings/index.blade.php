@extends('admin_lte.layout.app', ['activePage' => 'third_party_settings', 'titlePage' => __('Third Party Settings')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            @can('third_party_settings.create')
            <a href="{{ route('admin.third_party_settings.create') }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add
                </button>
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Sr. No.</th>
                    <th>Name</th>
                    <th>Url</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                @foreach($occuption as $key => $value)
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('third_party_settings.edit')
                            <a class="btn btn-info mr-1" href="{{ route('admin.third_party_settings.edit', $value->id) }}"><i class="far fa-edit"></i></a>
                            @endcan
                            @can('third_party_settings.delete')
                            <form action="{{ route('admin.third_party_settings.destroy', $value) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->name}}</td>
                    <td>{{substr($value->url, 0,50) . '...'}}</td>
                    <td>{{$value->method}}</td>
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
