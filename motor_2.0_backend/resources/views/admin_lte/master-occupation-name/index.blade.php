@extends('admin_lte.layout.app', ['activePage' => 'master-occupation-name', 'titlePage' => __('Master Occupation Name')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            @can('master_occupation_name.create')
            <a class="btn btn-primary mr-1 view" target="_blank" data-toggle="modal" data-target="#modal-default-store" data=""><i class="fa fa-plus-square" aria-hidden="true"></i> Insert New Occupation Name</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Sr. No.</th>
                    <th>Occupation Name</th>
                </tr>
            </thead>
            <tbody>
                @foreach($occuption as $key => $value)
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('master_occupation_name.edit')
                            <a class="btn btn-info mr-1 view" href="#" data-toggle="modal" data-target="#modal-default" data="{{ $value }}"><i class="far fa-edit"></i></a>
                            @endcan
                            @can('master_occupation_name.delete')
                            <form action="{{ route('admin.master-occupation-name.destroy', $value->id) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    </td>
                    <td>{{++$key}}</td>
                    <td>{{$value->occupation_name}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('admin.master-occupation-name.update', [1, 'data' => request()->all()]) }}" method="post" id="update-status">@csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Edit Occupation Name Details:</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                <input type="text" name="id" id="id"  hidden/>
                    <label>Occupation Name <span class="text-danger"> *</span></label>
                    <input type="text" name="occupation_name" id="occupation_name" class="form-control" required>
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

<!-- Modal 2-->
<div class="modal fade" id="modal-default-store" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <form action="{{ route('admin.master-occupation-name.store')}}" method="post" id="update-status">@csrf @method('POST')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Occupation Name Details:</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Occupation Name <span class="text-danger"> *</span></label>
                    <input type="text" name="occupation_name" id="occupation_name" class="form-control" required>
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

    $(function () {
    $("#data-table").DataTable({
      "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });


  $(document).on('click', '.view', function () {
        var data = JSON.parse($(this).attr('data'));

        $('#exampleModalLabel').html(`Edit ${data.occupation_name} Occuption Name Details:`);
        $("#occupation_name").val(data.occupation_name);
        $('#id').val(data.id);

        // update button dynamic name
        $('#update-rto-btn').html(`Update ${data.occupation_name} Occuption Name`);




        $('#showdata').html(data);
    });
</script>
@endsection('scripts')
