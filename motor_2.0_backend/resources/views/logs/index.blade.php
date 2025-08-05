@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Logs</h4>
                        <p class="card-description">

                        </p>
                        <form action="" method="GET">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="list">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="row">
                                    <div class="col-md-6 col-lg-3 col-xl-2">
                                        <div class="alert alert-danger mt-3 py-1">
                                            {{ session('error') }}
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!-- @csrf -->
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="active required" for="enquiryId">Enquiry Id</label>
                                        <input class="form-control" id="enquiryId" name="enquiryId" type="text"
                                            value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                                            placeholder="Enquiry Id" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Company</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="company">
                                            <option value="">Show All</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company }}"
                                                    {{ old('company', request()->company) == $company ? 'selected' : '' }}>
                                                    {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-3">
                                    <!-- <div class="form-group">
                                        <label>Section</label>
                                        <select class="form-control" name="section">
                                            <option value="">Show All</option>
                                            @foreach ($sections as $section)
                                                <option value="{{ $section }}"
                                                    {{ old('section', request()->section ?? '') == $section ? 'selected' : '' }}>
                                                    {{ $section }}</option>
                                            @endforeach
                                        </select>
                                    </div> -->
                                    <div class="form-group">
                                        <label>Transaction Type</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="transaction_type">
                                            <option value="">Show All</option>
                                            <option value="proposal"
                                                {{ old('transaction_type', request()->transaction_type) == 'proposal' ? 'selected' : '' }}>
                                                Proposal</option>
                                            <option value="quote"
                                                {{ old('transaction_type', request()->transaction_type) == 'quote' ? 'selected' : '' }}>
                                                Quote</option>
                                        </select>
                                    </div>
                                    <div class="form-group">        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="">From Date</label>
                                                <input type="text" name="from_date"   value="{{ old('from_date', request()->from_date) ? request()->from_date : Carbon\Carbon::now()->startOfMonth() }}"
                                                required id="" class="datepickers form-control" placeholder="From" autocomplete="off">                                            </div>                                            <div class="col-md-6">
                                                <label for="">To Date</label>
                                                <input type="text" name="to_date" value="{{ old('to_date', !empty(request()->to_date) ? request()->to_date : Carbon\Carbon::today()->format('Y-m-d')) }}" required id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>View Type</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="view_type">
                                            <option value="view"
                                                {{ old('view_type', request()->view_type) == 'view' ? 'selected' : '' }}>
                                                View</option>
                                            <option value="excel"
                                                {{ old('view_type', request()->view_type) == 'excel' ? 'selected' : '' }}>
                                                Excel</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <button class="btn btn-outline-primary" type="submit" style="margin-top: 30px;"><i
                                                class="fa fa-search"></i> Search</button>
                                    </div>
                                </div>
                                <div class="col-sm-3 text-right">
                                    <div class="form-group">
                                        <br>
                                        <div class="form-check">

                                            <input class="form-check-input" type="checkbox" value="Internal Service"
                                                id="internal_service"
                                                {{ old('internal_service', request()->internal_service ?? '') ? 'checked' : '' }}
                                                name="internal_service"">
                                            <label class="form-check-label ms-1" for="internal_service">
                                                Internal Service
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 grid-margin stretch-card">

                @if (!empty($logs ?? []))
                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table-striped table">
                                    <thead>
                                        <tr>
                                            <th class="text-right" scope="col">View Request Response</th>
                                            <th scope="col">#</th>
                                            <th scope="col">Company Name</th>
                                            <th scope="col">Section</th>
                                            <th scope="col">Product Name</th>
                                            <th scope="col">Method Name</th>
                                            <th scope="col">Transaction Type</th>
                                            <th scope="col">Created At</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($logs as $log)
                                            <tr>
                                                <td class="text-right">
                                                    <div class="btn-group" role="group">
                                                        @can('log.show')
                                                            <a class="btn btn-sm btn-primary"
                                                                href="{{ route('admin.log.show', ['log' => $log, 'transaction_type' => $log->transaction_type]) }}"
                                                                target="_blank"><i class="fa fa-eye"></i></a>
                                                            <!-- <button class="btn btn-info" data-toggle="modal" data-target="#modal-{{ $loop->iteration }}" type="button"><i class="fa fa-info-circle"></i></button> -->
                                                            {{-- <button class="btn btn-sm btn-success" type="button"
                                                                onclick="onClickDownload('{{ $log->company . '-' . $log->enquiry_id . '-' . $log->id }}','{{ json_encode($log->headers) }}', '{{ json_encode($log->request) }}','{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')"><i
                                                                    class="fa fa-arrow-circle-down"></i></button> --}}
                                                            <!-- <a class="btn-floating waves-effect waves-light btn modal-trigger" href="#modal-{{ $loop->iteration }}"><i class="">info_outline</i></a> -->
                                                            {{-- <!-- <button class="btn-floating waves-effect waves-light btn" id="dwn-btn-{{ $loop->iteration }}" onclick="onClickDownload('dwn-btn-{{ $loop->iteration }}', '{{ json_encode($log->request) }}', '{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')"><i class="material-icons">download</i></button> --> --}}
                                                            <a class="btn btn-sm btn-success"
                                                                href="{{ route('api.logs.view-download', ['type' => $log->transaction_type, 'id' => $log->id, 'enc' => enquiryIdEncryption($log->id), 'view' => 'download', 'with_headers' => 1]) }}"
                                                                target="_blank" {{-- onclick="onClickDownload('{{ $log->company . '-' . $log->enquiry_id . '-' . $log->id }}', '{{ json_encode($log->request) }}', '{{ json_encode($log->response) }}', '{{ json_encode($log->headers) }}', '{{ $log->endpoint_url }}')" --}}>
                                                                <i class="fa fa-arrow-circle-down"></i>
                                                            </a>
                                                        @endcan
                                                    </div>
                                                </td>
                                                <td scope="row">{{ $loop->iteration }}</td>
                                                <td>{{ strtoupper(str_replace('_', ' ', $log->company)) }}</td>
                                                <td>{{ $log->section }}</td>
                                                <td>{{ $log->product }}</td>
                                                <td>{{ $log->method_name }}</td>
                                                <td>{{ $log->transaction_type ?? 'N/A' }}</td>
                                                <td>{{ Carbon\Carbon::parse($log->start_time)->format('d-M-Y H:i:s A') }}
                                                </td>

                                            </tr>
                                            {{-- Button trigger modal --}}
                                            {{-- @ include('components.requestResponseModel',['log' => $log]) --}}
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="float-end mt-1">
                                {{ $logs->links() }}
                            </div>
                        </div>
                    </div>
                @else
                    <p>No Records or search for records</p>
                @endif
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('#policy_reports').DataTable();
            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });

        });

        function copToClipboard(modal) {
            var range = document.createRange();
            range.selectNode(document.getElementById(modal));
            window.getSelection().removeAllRanges(); // clear current selection
            window.getSelection().addRange(range); // to select text
            document.execCommand("copy");
            window.getSelection().removeAllRanges(); // to deselect
        }

        // Start file download.
        function onClickDownload(filename, headers, request, response, url) {
            let text = `URL :
${url}

Headers :
${headers}

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
