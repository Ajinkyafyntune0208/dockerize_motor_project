@extends('layout.app', ['activePage' => 'rc-report', 'titlePage' => __('RC Report')])
@section('content')
    <!-- partial -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">RC Report </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div class="form-group">
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
                                            <Label>From Date</Label>
                                            <input type="date" name="from" value="{{ old('from', request()->from) }}" id="" class="form-control" placeholder="From" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>To Date</Label>
                                            <input type="date" name="to" value="{{ old('to', request()->to ) }}" id="" class="form-control" placeholder="To" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>Report Type</Label>
                                            <select name="type" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                                <option {{ old('type', request()->type ) == 'view' ? 'selected' : "" }}>{{ 'view' }}</option>
                                                <option {{ old('type', request()->type ) == 'excel' ? 'selected' : "" }}>{{ 'excel' }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>Service Type</Label>
                                            <select name="service_type" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" required>
                                                {{-- <option value="" {{ old('service_type', request()->service_type ) == '' ? 'selected' : "" }}>{{ 'All' }}</option> --}}
                                                <option value="" disabled selected>Select Any One</option>
                                                <option value="Adrila Pro Service" {{ old('service_type', request()->service_type ) == 'Adrila Pro Service' ? 'selected' : "" }}>{{ __('Adrila Pro Service') }}</option>
                                                <option value="Adrila Lite Service" {{ old('service_type', request()->service_type ) == 'Adrila Lite Service' ? 'selected' : "" }}>{{ __('Adrila Lite Service') }}</option>
                                                <option value="Adrila Validate Service" {{ old('service_type', request()->service_type ) == 'Adrila Validate Service' ? 'selected' : "" }}>{{ __('Adrila Validate Service') }}</option>
                                                <option value="Fast Lane Service" {{ old('service_type', request()->service_type ) == 'Fast Lane Service' ? 'selected' : "" }}>{{ __('Fastlane') }}</option>
                                                <option value="Ongrid Service" {{ old('service_type', request()->service_type ) == 'Ongrid Service' ? 'selected' : "" }}>{{ __('Ongrid') }}</option>
                                                <option value="Signzy Service" {{ old('service_type', request()->service_type ) == 'Signzy Service' ? 'selected' : "" }}>{{ __('Signzy') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>Journey Type</Label>
                                            <select name="journey_type" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                                <option value="" {{ old('journey_type', request()->journey_type ) == '' ? 'selected' : "" }}>{{ 'All' }}</option>
                                                <option value="Input" {{ old('journey_type', request()->journey_type ) == 'Input' ? 'selected' : "" }}>{{ __('Input') }}</option>
                                                <option value="Proposal" {{ old('journey_type', request()->journey_type ) == 'Proposal' ? 'selected' : "" }}>{{ __('Proposal') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center h-100">
                                            <input type="submit" class="btn btn-outline-info btn-sm w-100">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="table-responsive">
                            @if (!$reports->isEmpty())
                            <table class="table table-striped" id="policy_reports">
                                <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Req/Res</th>
                                    <th scope="col">Transaction Type</th>
                                    <th scope="col">RC Number</th>
                                    <th scope="col">Response Time</th>
                                    <th scope="col">Created At</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($reports as $index => $report)
                                    <tr>
                                        <td>{{ ++$index }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('admin.rc-report.show', ['rc_report' => $report]) }}"
                                                target="_blank"><i class="fa fa-eye"></i></a>
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
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">
                                <p scope="col">Total Result found: {{$reports->total()}}</p>
                            </div>
                            <div class="float-end mt-1">
                                {{ $reports->links() }}
                            </div>
                            @else
                                <p style="text-align: center;">No Records found.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            // $('#policy_reports').DataTable();
            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });
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
@endpush
