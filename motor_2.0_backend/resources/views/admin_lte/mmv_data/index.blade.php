@extends('admin_lte.layout.app', ['activePage' => 'mmv-data', 'titlePage' => __('MMV Data')])
@section('content')
<div class="card">   
    @can('mmv_data.edit')
    <div class="card-header">
        <div class="card-tools">
            <a href="{{route('admin.sync_mmv')}}" class="btn btn-primary">Update MMV</a>
        </div>
    </div>
    @endcan
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="downloadForm" action="{{ url()->current() }}" method="get">
            <div class="form-group row">
                <label for="file_name" class="col-form-label">File Name</label> 
                <div class="col-sm-4">
                    <select name="file_name" id="" class="select2 w-100 form-control" data-live-search="true" required>
                        <option value="">Nothing selected</option>
                        @foreach($files as $file)
                        <option {{ request()->file_name == $file ? 'selected' : '' }} value="{{ $file }}">{{ Str::title(implode(' ',explode('_', Str::substr(Arr::last(explode('/', $file)),0,-5)))) }}</option>
                        @endforeach
                    </select>
                </div>  
                <div class="col-sm-6">
                    <input id="download" type="hidden" name="file_download" value="">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Submit</button>
                    <button type="submit" class="btn btn-primary" onclick="downloadFile()"><i class="fa fa-download"></i> Download</button>
                    <input type="hidden" name="form_submit" value=true>
                </div>
            </div>
        </form>  
    </div>
</div>
@if(!empty($data))
<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            @foreach($data as $key => $row)
            @if ($loop->first)         
            <thead>
                <tr>
                    @foreach($row as $key => $dd)
                    <th>
                        {{ $key }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @endif
                <tr>
                    @foreach($row as $dd)
                    <td>
                        {{ $dd }}
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
<!-- Modal -->
{{-- <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Update MMV Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{route('admin.mmv-data.store')}}" id="car_getdata" name="car_getdata" method="POST">@csrf @method('POST')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group p-3">
                                <label>Select Broker</label>
                                <select class="selectpicker w-100" data-style="btn btn-primary" data-live-search="true" id="url" name="url">
                                    <option value="">Nothing selected</option>
                                    @foreach($borker_details as $borker_detail)
                                    <option {{ old('url', request()->url) == $borker_detail->backend_url ? 'selected' : '' }} value="{{ $borker_detail->backend_url }}">{{ $borker_detail->name . ' - ' . $borker_detail->environment }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group p-3">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update MMV</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> --}}
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
    document.getElementById('downloadForm').addEventListener('submit', function(event) {
        var file_name = document.getElementById('file_name').value;
        var action = "{{ route('admin.mmv-data-excel') }}?file_name=" + encodeURIComponent(file_name);
        this.action = action;
    });
    function downloadFile(){
       $("#download").val("downloadFile");
    }
</script>
@endsection
