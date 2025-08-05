@extends('admin_lte.layout.app', ['activePage' => 'company', 'titlePage' => __('Manage IC logo')])
@section('content')
<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Sr. No.</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Logo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($companies as $key => $value)
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('manage_ic_logo.show')
                            <a href="{{ $value->logo }}" class="btn btn-success mr-1" target="_blank"><i class="fa fa-eye"></i></a>
                            @endcan
                            @can('manage_ic_logo.edit')
                            <a class="btn btn-info edit-btn" href="#" data-id="{{ $value->company_id }}" data-toggle="modal" data-target="#modal-default"><i class="far fa-edit"></i></a>
                            @endcan
                            @can('manage_ic_logo.delete')
                            <form action="{{ route('admin.role.destroy', $value) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->company_name}}</td>
                    <td>{{$value->company_alias}}</td>
                    <td>
                        <a href="{{ $value->logo }}" target="_blank">
                            <img src="{{ $value->logo }}" class="img-fluid" style="width: 70px;" alt="{{ $value->company_name }}">
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="" method="post" enctype="multipart/form-data" id="update-status">@csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalCenterTitle">Upload Logo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Upload Logo</label> <br>
                    <input type="file" name="logo" class="btn btn-primary">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
  </div>
</div>
@endsection('content')
@section('scripts')
<script>
    $(document).on('click', '.edit-btn', function () {
        const companyId = $(this).data('id');
        const formAction = "{{ route('admin.company.update', ':id') }}".replace(':id', companyId);
        $('#update-status').attr('action', formAction);
    });

    
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
