@extends('admin_lte.layout.app', ['activePage' => 'ckyc-redirection-logs', 'titlePage' => __('CKYC Redirection Logs')])
@section('content')
<div class="card card-primary">
    <!-- form start -->
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Enquiry Id <span class="text-danger"> *</span></label>
                        <input class="form-control" id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" >
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Section <span class="text-danger"> *</span></label>
                        <select class="form-control select2" name="section" data-live-search="true">
                            <option value="">Show All</option>
                            <option value="motor">Motor</option>
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <input type="hidden" name="paginate" value="30">
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if (is_array($logs))
<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table class="table-striped table" id="result">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th class="text-right" scope="col">View</th>
                        <th scope="col">Trace Id</th>
                        <th scope="col">Company Alias</th>
                        <th scope="col">Logs From</th>
                        {{-- <th scope="col">Request</th>
                                            <th scope="col">Responce</th>  --}}
                        <th scope="col">Response Time</th>
                        <th scope="col">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td class="text-center">
                            <a class="btn btn-sm btn-primary" class="btn btn-primary float-end btn-sm" href="{{ url('admin/ckyc-redirection-logs/' . $log['id'] . '/' . str_replace(' ','', $log['log_map'])) }}" target="_blank"><i class="fa fa-eye"></i></a>
                        </td>
                        <td>{{ $log['trace_id'] }}</td>
                        <td>{{ $log['company_alias']}}</td>
                        <td>{{ $log['log_map'] }}</td>
                        {{-- <td>{{ $log['request'] ? substr($log['request'], 0, 50) . '...' : 'NA'}}</td>
                        <td>{{ $log['response'] ? substr($log['response'], 0, 50) . '...' : 'NA' }}</td> --}}
                        <td>{{ ($log['response_time'] ?? 'NA' ). 's' }}</td>
                        <td>{{ Carbon\Carbon::parse($log['created_at'])->format('d-M-Y h:i:s A') }}
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<p>No Records or search for records</p>
@endif
@endsection('content')

@section('scripts')
<script>
$("#result").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": true,
        "buttons": ["copy", "csv", "excel", "pdf", "print", {
            extend: 'colvis'
        }],
        "pageLength": 1000
    }).buttons().container().appendTo('#result_wrapper .col-md-6:eq(0)');
</script>
@endsection('scripts')
