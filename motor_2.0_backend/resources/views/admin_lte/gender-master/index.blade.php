@extends('admin_lte.layout.app', ['activePage' => 'gender-master', 'titlePage' => __('Gender Mapping')])
@section('content')
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.gender-master.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-2">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active required" for="excelFile">Choose excel file</label>
                                    <input type="file" name="excelfile" required>
                                </div>
                            </div>
                            <div class="col-sm-9 text-right">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <a class="btn btn-primary mb-3" href="{{route('admin.gender-master.create')}}">Download Excel File Format</a>
                    <div class="table-responsive">
                        <table class="table table-striped" id="response_log">
                            <thead>
                                <tr>
                                    <th scope="col">Sl.no</th>
                                    @foreach($columnNames as $key => $columnName)
                                    <th scope="col">{{ $columnName }}</th>
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
    </div>
    @endsection('content')
    @section('scripts')
    <script>
        $(function () {
        $("#data-table").DataTable({
            "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
          "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
      });
    </script>
    @endsection('scripts')
