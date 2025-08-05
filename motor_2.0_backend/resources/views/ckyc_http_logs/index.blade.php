@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">CKYC Logs</h4>
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
                            <!-- @csrf -->
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="active required" for="enquiryId">Enquiry Id</label>
                                        <input class="form-control" id="enquiryId" name="enquiryId" type="text"
                                            value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                                            placeholder="Enquiry Id" required aria-required="true">
                                    </div>

                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Company</label>
                                        <br>
                                        <select class="selectpicker" name="company" data-live-search="true">
                                            <option value="">Show All</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company }}"
                                                    {{ old('company', request()->company) == $company ? 'selected' : '' }}>
                                                    {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-3 text-right">
                                    <div class="form-group">
                                        <button class="btn btn-outline-primary" type="submit" style="margin-top: 30px;"><i
                                                class="fa fa-search"></i> Search</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 grid-margin stretch-card">

                @if (!empty($logs))
                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table-striped table">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th class="text-right" scope="col">View</th>
                                            <th scope="col">Trace Id</th>
                                            <th scope="col">Url</th>
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
                                                <td class="text-right">
                                                    <a class="btn btn-sm btn-primary"
                                                        class="btn btn-primary float-end btn-sm"
                                                        href="{{ url('admin/ckyc-logs/' . $log['id'] . '/' . str_replace(' ','', $log['log_map'])) }}" target="_blank"><i
                                                            class="fa fa-eye"></i></a>
                                                </td>
                                                <td>{{ $log['trace_id'] }}</td>
                                                <td>{{ Str::substr($log['url'] ?? '', 0, 50) }}</td>
                                                <td>{{ $log['log_map'] }}</td>
                                                {{--   <td>{{ $log['request'] ? substr($log['request'], 0, 50) . '...' : 'NA'}}</td>
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
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.table').DataTable();
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
