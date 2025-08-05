@extends('admin_lte.layout.app', ['activePage' => 'finance-agreement-master', 'titlePage' => __('Financier Agreement Type')])
@section('content')
<div class="row">
          <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.finance-agreement-master.store', rand()) }}" enctype="multipart/form-data"
                                    method="post">@csrf
                        <div class="row mb-2">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active required" for="excelFile">Choose excel file</label>
                                    <input type="file" name="excelfile" required>
                                    @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-sm-9 text-right">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <a class="btn btn-primary" href="{{route('admin.finance-agreement-master.create')}}">Download Excel File Format</a>
                </div>
            </div>
            </div>

          <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Financier Agreement List</h3>
                </div>
                <div class="card-body">
                    <table id="data-table" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                @foreach($columnNames as $key => $columnName)
                                <th>{{ $columnName }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                                @foreach($datas as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    @foreach($columnNames as $columnName)
                                    <td>{{ $data->$columnName }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
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
    $(function () {
    $("#data-table").DataTable({
        "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
        "buttons": ["copy", "csv", "excel",{
                    extend: 'pdfHtml5',
                    orientation : 'landscape',
                    pageSize : 'A2',
                },"print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(2))'
                },
    ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });
</script>
@endsection('scripts')