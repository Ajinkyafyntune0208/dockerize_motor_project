@extends('admin_lte.layout.app', ['activePage' => 'master-sync-logs', 'titlePage' => __('MDM Sync Logs')])
@section('content')
<div class="card">
    <div class="card-body">
        <form  action="" method="GET" >
            @csrf
            <input type="hidden" name="per_page" id="per_page" value="">
            <div class="row align-items-center">
                <div class="col-sm-4 form-group">
                    <label class="font-weight-bold" for="bid">Master Name</label>
                    <input type="text" class="form-control" name="master_name" id="master_name" value="{{ old('master_name', request()->master_name) }}" >
                </div>
                <div class="col-md-2 form-group">
                    <label for="">From Date</label>
                    <input type="text" name="from_date"  
                        value="{{ old('from_date', !empty(request()->from_date) ? request()->from_date : Carbon\Carbon::now()->startOfMonth()->format('Y-m-d')) }}"
                        id="" class="datepickers form-control" placeholder="From"
                        autocomplete="off"
                    >                                   
                </div>
                <div class="col-md-2 form-group">
                    <label for="">To Date</label>
                    <input type="text" name="to_date" 
                        value="{{ old('to_date', !empty(request()->to_date) ? request()->to_date : Carbon\Carbon::today()->format('Y-m-d')) }}" 
                        id="" class="datepickers form-control" placeholder="To" 
                        autocomplete="off"
                    >
                </div>
                <div class="col-md-2 form-group">
                    <label for="">Status</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="status" id="status">
                        <option value="" {{ isset(request()->status) ? '' : 'selected'}}> Select Status</option>
                        <option {{old('status', request()->status) == 'success' ? 'selected' : '' }} value="success">Success</option>
                        <option {{old('status', request()->status) == 'failure' ? 'selected' : '' }} value="failure">Failed</option>
                        <option {{old('status', request()->status) == 'pending' ? 'selected' : '' }} value="pending">Pending</option>
                    </select>
                </div>
                <input type="hidden" name="form_submit" value=true>
                <div class="col-md-2">
                    <button type="submit" id="submit" class="btn btn-primary" style="margin-top:8%"><i class="fa fa-search m-1"></i> Search</button>
                </div>
            </div>   
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        
        @if ($mdmlogs->isEmpty())
            <div class="alert alert-warning mt-3"> <i class="fa fa-warning"></i> No records found!</div>
        @else
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Master ID</th>
                        <th>Master Name</th>
                        <th>Total Rows</th>
                        <th>Rows Inserted</th>
                        <th>Message</th>
                        <th>Created Date</th>
                        <th>Updated Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mdmlogs as $key => $value)
                        <tr>
                            <th>{{$loop->iteration}}</th>
                            <td class="text-center text-{{($value->status == 'success') ? 'success' : (($value->status == 'pending') ? 'warning' : 'danger')}}">
                                {{ strtoupper($value->status) }}
                            </td>
                            <td>{{ $value->master_id }}</td>
                            <td>{{ $value->master_name ?? 'NA' }}</td>
                            <td>{{ $value->total_rows }}</td>
                            <td>{{ $value->rows_inserted }}</td>
                            <td>{{$value->message }}</td>
                            <td>{{ $value->created_at }}</td>
                            <td>{{ $value->updated_at }}</td>
                        </tr>
                    @endforeach
                
                </tbody>
            </table>

            <!-- <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">

                <p>Total Result found: {{$mdmlogs->total()}}</p>
                <p>Showing records per page: {{$mdmlogs->count()}}</p>
                <div>
                    @if(!$mdmlogs->isEmpty())
                        {{ $mdmlogs->appends(request()->query())->links() }}
                    @endif
                </div>
            </div> -->
        @endif
    </div>
</div>
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
                    columns: 'th:not(:nth-child(4))'
                },
    ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });
    </script>
    <script>
         $(document).ready(function() {
            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                autoUpdateInput: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            }).datepicker('update',null);;

        });
    </script>
@endsection('scripts')
