@extends('admin_lte.layout.app', ['activePage' => 'ckyc-logs', 'titlePage' => __('CKYC Logs')])
@section('content')
<div class="card card-primary">
    <!-- form start -->
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Enquiry Id <span class="text-danger"> *</span></label>
                        <input class="form-control" id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" required aria-required="true">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Company <span class="text-danger"> *</span></label>
                        <select class="form-control select2" name="company" data-live-search="true">
                            <option value="">Show All</option>
                            @foreach ($companies as $company)
                            <option value="{{ $company }}" {{ old('company', request()->company) == $company ? 'selected' : '' }}>
                                {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}
                            </option>
                            @endforeach
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
@if (!empty($logs) && !is_string($logs))
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
                            <a class="btn btn-sm btn-primary" class="btn btn-primary float-end btn-sm" href="{{ url('admin/ckyc-logs/' . $log['id'] . '/' . str_replace(' ','', $log['log_map'])) }}" target="_blank"><i class="fa fa-eye"></i></a>
                        </td>
                        <td>{{ $log['trace_id'] }}</td>
                        <td>{{ Str::substr($log['url'] ?? '', 0, 50) }}</td>
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