@extends('admin_lte.layout.app', ['activePage' => 'sql_runner', 'titlePage' => __('Query builder')])
@section('content')
<div class="card">
    <div class="card-body">
        <form  action="" method="POST" id="sql" onsubmit="return validateForm()">
            @csrf
            <input type="hidden" name="per_page" id="per_page" value="">
            <div class="align-items-center">
                <label class="font-weight-bold mb-2 required" for="sql_query">Enter your Sql Query</label>
                <div class="form-group">
                    <p class="d-flex flex-row align-items-center">
                        <span class="mx-2" style="font-size:16px;">SELECT</span>
                        <span class="w-100">
                            <textarea class="form-control" name="sql_query" id="sql_query"  rows="100" style="height: 100px;" required> {{ old('sql_query', request()->sql_query) }}</textarea>
                            <label id="sql_validation" hidden class="text-danger">Sql Query is required!</label>
                        </span>
                    </p>
                </div>
            </div>  
            <div class="row mx-5">
                <div class="col-sm-2">
                    <button type="submit" id="submit" onclick="unSetFunction()" class="btn btn-primary ml-3" value="unset"><i class="fa fa-code mx-1"></i>Run</button>
                </div>
                <div class="col-sm-5">
                    <input type="hidden" name="download" id="runsqldownload">
                    <button type="submit" id="data" onclick="setFunction()" class="btn btn-primary" value="rundownload"><i class="fa fa-code mx-1"></i>Run and Download</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (Session::has('errors'))     
    <div class="alert alert-{{Session::get('class')}}">
        {{Session::get('errors')}}
    </div>  
@endif

@if ( isset($headings) && isset($records)  )
<div class="card">
    <div class="card-body">  
        @if (empty($records))
        <div class="alert alert-warning">No records found</div>
        @else
        <table id="data-table" class="table table-bordered table-hover">
            @if (!empty($headings))
            <thead>
                <tr>
                    <th>#</th>
                    @foreach ($headings as $value)
                        <th>{{$value}}</th>
                    @endforeach
                </tr>
            </thead>
            @endif
            <tbody>
                @if (!empty($records))
                    @foreach ($records as $data)
                        <tr>
                            <th>{{$loop->iteration}}</th>
                            @foreach ($data as $value)
                                <td>{{ $value}}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        @endif
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    $(function () {
    $("#data-table").DataTable({
      "responsive": false, "lengthChange": true, "autoWidth": true,
       scrollX: true,
      "buttons": ["copy", "csv", "excel",{
                    extend: 'pdfHtml5',
                    orientation : 'landscape',
                    pageSize : 'A2',
                },"print",  {
                    extend: 'colvis',
                    columns: 'th:nth-child(n+3)'
                },
    ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });
</script>
    <script> 
        function validateForm() {
            var sqlQuery = document.getElementById('sql_query').value;
            if (sqlQuery.trim() === '') {
                $('#sql_validation').attr('hidden',false)
                return false;
            }
            return true;
        }
        function setFunction() { 
            var dd = document.getElementById('data').value;
            console.log(dd);
            $('#runsqldownload').val(dd)
        } 
        function unSetFunction() { 
            var dd = document.getElementById('submit').value;
            console.log(dd);
            $('#runsqldownload').val(dd)
        }
    </script>
@endsection('scripts')
