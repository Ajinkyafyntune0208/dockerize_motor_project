@extends('admin_lte.layout.app', ['activePage' => 'renewal-data-migration', 'titlePage' => __('View upload process log')])
@section('content')
<div class="card">
    <div class="card-body">
        <div class="form-group">
            <form action="" method="get">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <Label for="EnquiryID">Enquiry ID</Label>
                            <input type="text" name="enquiryId" value="{{ old('enquiryId', request()->enquiryId) }}" id="EnquiryID" class="form-control" placeholder="Enquiry ID" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <Label for="RCnumber">RC Number</Label>
                            <input type="text" name="rcNumber" value="{{ old('rcNumber', request()->rcNumber) }}" id="RCnumber" class="form-control" placeholder="RC Number" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <Label for="PolicyNumber">Policy Number</Label>
                            <input type="text" name="policyNumber" value="{{ old('policyNumber', request()->policyNumber) }}" id="PolicyNumber" class="form-control" placeholder="Policy Number" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <Label for="FromDate">From Date</Label> 
                            <input type="date" name="from" value="{{ old('from', request()->from) }}" id="FromDate" class="datepickers form-control" placeholder="From" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <Label for="ToDate">To Date</Label>
                            <input type="date" name="to" value="{{ old('to', request()->to ) }}" id="ToDate" class="datepickers form-control" placeholder="To" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>View Type</label>
                            <select name="viewType" id="viewType"class="select2 w-100 form-control" data-live-search="true">
                                <option value="">Nothing selected</option>
                                <option {{ old('broker_url', request()->viewType) == 'view' ? 'selected' : '' }} value="view">View</option>
                                <option {{ old('broker_url', request()->viewType) == 'excel' ? 'selected' : '' }} value="excel">Excel</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group h-100">
                            <input type="submit" class="btn btn-primary" style="margin-top:11%;">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        @if (!$reports->isEmpty())
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col"></th>
                <th scope="col">Enquiry ID</th>
                <th scope="col">Existing Enquiry ID</th>
                <th scope="col">RC Number</th>
                <th scope="col">Policy Number</th>
                <th scope="col">Status</th>
                <th scope="col">Created At</th>
            </tr>
            </thead>
            <tbody>
            @foreach($reports as $index => $report)
                <tr>
                    <td>{{ ++$index }}</td>
                    <td>
                        @can('view_upload_process_logs.show')
                        <a class="btn btn-sm btn-primary"
                            href="{{ route('admin.renewal-data-migration.show', ['renewal_data_migration' => $report->id]) }}"
                            target="_blank"><i class="fa fa-eye"></i></a>
                        </a>
                        <a class="btn btn-sm btn-success"
                            href="{{ route('admin.renewal-data-migration.download', ['id' => $report->id]) }}"
                            target="_blank">
                            <i class="fa fa-arrow-circle-down"></i>
                        </a>
                        @endcan
                    </td>
                    <td>{{ !empty($report['user_product_journey_id']) ? customEncrypt($report['user_product_journey_id']) : '-' }}</td>
                    <td>
                        {{ !empty($report->user_product_journey->old_journey_id) ? customEncrypt($report->user_product_journey->old_journey_id) : '-' }}
                    </td>
                    <td>{{ $report['registration_number'] ?? '-' }}</td>
                    <td>{{ $report['policy_number'] ?? '-' }}</td>
                    <td>{{ $report['status'] }}</td>
                    <td>{{ $report['created_at']}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <p style="text-align: center;">No Records found.</p>
        @endif
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).ready(function() {
        if($("#data-table").length){
            $(function () {
                $("#data-table").DataTable({
                    "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
                }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
            });
        }
    });
</script>
@endsection
