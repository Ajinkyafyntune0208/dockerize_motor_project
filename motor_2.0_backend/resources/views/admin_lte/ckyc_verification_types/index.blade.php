@extends('admin_lte.layout.app', ['activePage' => 'ckyc_verification_types', 'titlePage' => __('CKYC Verification Types')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="" method="GET" name="ckyc_verification">
            @csrf
            <input type="hidden" name="per_page" id="per_page" value="">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <input id="search" name="search" type="search"
                            value="{{ old('search', request()->search ?? null) }}" class="form-control"
                            placeholder="search by message "required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <button class="btn btn-primary" id="submit">submit</button>
                    </div>
                </div>
                <div class="col-sm-3">
                    @can('ckyc_verification_types.create')
                    <a href="{{ route('admin.ckyc_verification_types.create') }}" class="btn btn btn-primary">Add Ckyc
                        verification </a>
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
                    <th>Company_alias</th>
                    <th>Mode</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ckyc_verification_data as $key => $value)
                <tr>
                    <td>

                    <!-- <div class="info-box"> -->
                        <div class="btn-group btn-group-toggle">
                            @can('ckyc_verification_types.edit')
                            <a class="btn btn-info mr-1" href="#" onclick = "editDetails('{{$value->company_alias}}','{{$value->mode}}','{{encrypt($value->id)}}')" data-toggle="modal" data-target="#modal-default" ><i class="far fa-edit"></i></a>
                            @endcan
                            @can('ckyc_verification_types.delete')
                            <form action="{{ route('admin.ckyc_verification_types.destroy', encrypt($value->id)) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    <!-- </div> -->
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->company_alias}}</td>
                    <td>{{$value->mode}}</td>
                    <td>{{$value->created_at}}</td>
                    <td>{{$value->updated_at}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>



<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.ckyc_verification_types.update',encrypt($value->id)) }}" method="post" id="update-status">@csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalCenterTitle">CKYC Verification Type</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="active required" for="label">Company Alias</label>
                    <input type="text" class="form-control" id="company_alias"
                        name="company_alias" disabled>
                    @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="active required" for="label">Mode</label>
                    <select class="form-control select2" style="width:100%;" id="data"
                        name="mode">
                        <option value="api">Api</option>
                        <option value="redirection">Redirection</option>
                    </select>
                    @error('active')<span class="text-danger">{{ $message }}</span>@enderror
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



<!-- Modal -->
<!-- <div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action=""
        method="POST" class="mt-3" name="add_config">
        @csrf @method('PUT')
        <input type="hidden" class="form-control" id="id" name="id">
        <div class="row mb-3">
            <div class="col-sm-6">
                <div class="form-group">

                </div>
            </div>
            <div class="col-sm-6">

            </div>
            <div class="d-flex justify-content-center">
                <button type="submit" class="btn btn-outline-primary"
                    style="margin-top: 30px;">Submit</button>
            </div>
        </div>
    </form>
  </div>
</div> -->
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


  function editDetails(company_alias,mode,id){
        $('#company_alias').val(company_alias);
        $('#modenew').val(mode);
        $('#id').val(id);
        $('#data').val(mode);
        // $('#data').selectpicker('refresh');
        // $('#modal-default').modal("show");
    }
</script>
@endsection('scripts')
