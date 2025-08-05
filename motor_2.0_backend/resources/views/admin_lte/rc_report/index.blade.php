@extends('admin_lte.layout.app', ['activePage' => 'rc-report', 'titlePage' => __('RC Report')])
@section('content')

@if (!empty($errors) && count($errors) > 0)
    <div class="alert alert-danger">
        <ul class="list">
            @foreach ($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="card">
    <div class="card-body">
        <form action="" method="get">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <Label>RC Number</Label>
                        <input type="text" name="rc_number" value="{{ old('rc_number', request()->rc_number) }}" id="" class="form-control" placeholder="RC Number" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <Label class="required" for="from">From Date</Label>
                        <input required type="date" name="from" value="{{ old('from', request()->from) }}" id="from" class="form-control" placeholder="From" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <Label class="required" for="to">To Date</Label>
                        <input required type="date" name="to" value="{{ old('to', request()->to ) }}" id="to" class="form-control" placeholder="To" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <Label>Report Type</Label>
                        <select name="type" data-actions-box="true" class="select2 form-control w-100" data-live-search="true">
                            <option {{ old('type', request()->type ) == 'view' ? 'selected' : "" }}>{{ 'view' }}</option>
                            <option {{ old('type', request()->type ) == 'excel' ? 'selected' : "" }}>{{ 'excel' }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <Label>Service Type</Label>
                        <select name="service_type" data-actions-box="true" class="select2 form-control w-100" data-live-search="true" required>
                            {{-- <option value="" {{ old('service_type', request()->service_type ) == '' ? 'selected' : "" }}>{{ 'All' }}</option> --}}
                            <option value="" disabled selected>Select Any One</option>
                            <option value="Adrila Pro Service" {{ old('service_type', request()->service_type ) == 'Adrila Pro Service' ? 'selected' : "" }}>{{ __('Adrila Pro Service') }}</option>
                            <option value="Adrila Lite Service" {{ old('service_type', request()->service_type ) == 'Adrila Lite Service' ? 'selected' : "" }}>{{ __('Adrila Lite Service') }}</option>
                            <option value="Adrila Validate Service" {{ old('service_type', request()->service_type ) == 'Adrila Validate Service' ? 'selected' : "" }}>{{ __('Adrila Validate Service') }}</option>
                            <option value="Fast Lane Service" {{ old('service_type', request()->service_type ) == 'Fast Lane Service' ? 'selected' : "" }}>{{ __('Fastlane') }}</option>
                            <option value="Ongrid Service" {{ old('service_type', request()->service_type ) == 'Ongrid Service' ? 'selected' : "" }}>{{ __('Ongrid') }}</option>
                            <option value="Signzy Service" {{ old('service_type', request()->service_type ) == 'Signzy Service' ? 'selected' : "" }}>{{ __('Signzy') }}</option>
                            <option value="Surepass Service" {{ old('service_type', request()->service_type ) == 'Surepass Service' ? 'selected' : "" }}>{{ __('Surepass') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <Label>Journey Type</Label>
                        <select name="journey_type" data-actions-box="true" class="select2 form-control w-100" data-live-search="true">
                            <option value="" {{ old('journey_type', request()->journey_type ) == '' ? 'selected' : "" }}>{{ 'All' }}</option>
                            <option value="Input" {{ old('journey_type', request()->journey_type ) == 'Input' ? 'selected' : "" }}>{{ __('Input') }}</option>
                            <option value="Proposal" {{ old('journey_type', request()->journey_type ) == 'Proposal' ? 'selected' : "" }}>{{ __('Proposal') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center h-100">
                        <input type="submit" class="btn btn-primary w-100" style="margin-top:5%">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">

    <div class="table-responsive">
            @if (isset($reports) && !$reports->isEmpty())
            <table class="table table-striped" id="policy_reports">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Req/Res</th>
                    <th>Transaction Type</th>
                    <th>RC Number</th>
                    <th>Response Time</th>
                    <th>Created At</th>
                </tr>
                </thead>
                <tbody>
                @foreach($reports as $index => $report)
                    <tr>
                        <td>{{ ++$index }}</td>
                        <td>
                            @can('rc_report.show')
                            <a class="btn btn-sm btn-primary"
                                href="{{ route('admin.rc-report.show', ['rc_report' => $report]) }}"
                                target="_blank"><i class="fa fa-eye"></i></a>
                                @endcan
                            {{-- <a class="btn btn-sm btn-success"
                                href="{{ route('api.rc_reports.view-download', ['type' => $report->transaction_type, 'id' => $report->id, 'view' => 'download']) . '?with_headers=1' }}"
                                target="_blank">
                                <i class="fa fa-arrow-circle-down"></i>
                            </a> --}}
                        </td>
                        <td>{{ $report['transaction_type'] }}</td>
                        <td>{{ $report['request'] }}</td>
                        <td>{{ $report['response_time'] }}</td>
                        <td>{{ $report['created_at'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="float-end mt-1">
                {{ $reports->links() }}
            </div>
            @else
                <p style="text-align: center;">No Records found.</p>
            @endif
        </div>
    </div>
</div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            // $('#policy_reports').DataTable();
        })

        function onClickDownload(filename, request, response, url) {
            let text = `URL :
            ${url}


            Request :
            ${request}


            Response :
            ${response}`;


            var filename = "request_response " + filename + ".txt";
            var element = document.createElement('a');
            element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
            element.setAttribute('download', filename);
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }
    </script>
@endsection
