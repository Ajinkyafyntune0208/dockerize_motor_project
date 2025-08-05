@extends('admin_lte.layout.app', ['activePage' => 'ckyc_not_a_failure_cases', 'titlePage' => __('CKYC Not A Failure Cases')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="" method="GET" name="ckyc_failure">
            @csrf
            <input type="hidden" name="per_page" id="per_page" value="">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <input id="search" name="search" type="text"
                            value="{{ old('search', request()->search ?? null) }}"
                            class="form-control" placeholder="search by message">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                            <button class="btn btn-primary" id="submit">submit</button>
                    </div>
                </div>
                <div class="col-sm-3">
                    @can('ckyc_not_a_failure_cases.create')
                    <a class="btn btn-primary" href="{{ route('admin.ckyc_not_a_failure_cases.create') }}">+ Add CKYC Failure message</i></a>
                    @endcan
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Sr.No.</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($CKYCNotAFailureCasess as $key => $value)
                <tr>
                    <td>

                    <!-- <div class="info-box"> -->
                        <div class="btn-group btn-group-toggle">
                            @can('ckyc_not_a_failure_cases.edit')
                            <a class="btn btn-info mr-1" href="{{ route('admin.ckyc_not_a_failure_cases.edit', $value->id) }}"><i class="far fa-edit"></i></a>
                            @endcan
                            @can('ckyc_not_a_failure_cases.delete')
                            <form action="{{ route('admin.ckyc_not_a_failure_cases.destroy', $value) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    <!-- </div> -->
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->type}}</td>
                    <td>{{$value->message}}</td>
                    <td>{{($value->active == 0) ? 'Inactive' : 'Active'}}</td>
                    <td>{{$value->created_at}}</td>
                    <td>{{$value->updated_at}}</td>
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
                    columns: 'th:not(:nth-child(4))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });

</script>
@endsection('scripts')
