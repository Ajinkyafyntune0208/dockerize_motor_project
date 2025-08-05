@extends('admin_lte.layout.app', ['activePage' => 'role', 'titlePage' => __('Role')])
@section('content')
<div class="card">
    <div class="card-header">

        <div class="card-tools">
            @can('role.create')
            <a href="{{ route('admin.role.create') }}">
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
                    <th>Sr.No.</th>
                    <th>Name</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $key => $value)
                    @if (!(auth()->user()->email === 'motor@fyntune.com')  && $value->name === 'webservice')
                        @continue
                    @endif
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('role.edit')
                            <a class="btn btn-info mr-1" href="{{ route('admin.role.edit', $value) }}"><i class="far fa-edit"></i></a>
                            @endcan
                            @if($value->name != 'webservice')
                            @can('role.delete')
                            <form action="{{ route('admin.role.destroy', $value) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                            @endif
                        </div>
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->name}}</td>
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
      "responsive": false, "lengthChange": true, "autoWidth": false,
       scrollX: true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });
</script>
@endsection('scripts')
