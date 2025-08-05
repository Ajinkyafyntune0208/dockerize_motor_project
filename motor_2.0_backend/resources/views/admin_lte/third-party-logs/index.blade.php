@extends('admin_lte.layout.app', ['activePage' => 'log-third-paty-payment', 'titlePage' => __('Third Party Payment Logs')])
@section('content')
<div class="card">
    <div class="row">
        <!-- left column -->
        <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
                <!-- form start -->
                <div class="card-body">
                    <form action="" method="GET" name="serverErrorForm">
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
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label>Enquiry Id</label>
                                    <input class="form-control" id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" required>
                                    @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <!-- /.card -->
        </div>
        <!--/.col (left) -->

    </div>
</div>
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
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.log.show', ['log' => $log, 'transaction_type' => $log->transaction_type]) }}" target="_blank"><i class="fa fa-eye"></i></a>
                                <!-- <button class="btn btn-info" data-toggle="modal" data-target="#modal-{{ $loop->iteration }}" type="button"><i class="fa fa-info-circle"></i></button> -->
                                {{-- <button class="btn btn-sm btn-success" type="button"
                                                                onclick="onClickDownload('{{ $log->company . '-' . $log->enquiry_id . '-' . $log->id }}','{{ json_encode($log->headers) }}', '{{ json_encode($log->request) }}','{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')"><i class="fa fa-arrow-circle-down"></i></button> --}}
                                <!-- <a class="btn-floating waves-effect waves-light btn modal-trigger" href="#modal-{{ $loop->iteration }}"><i class="">info_outline</i></a> -->
                                {{-- <!-- <button class="btn-floating waves-effect waves-light btn" id="dwn-btn-{{ $loop->iteration }}" onclick="onClickDownload('dwn-btn-{{ $loop->iteration }}', '{{ json_encode($log->request) }}', '{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')"><i class="material-icons">download</i></button> --> --}}
                                <a class="btn btn-sm btn-success" href="{{ route('api.logs.view-download', ['type' => $log->transaction_type, 'id' => $log->id, 'enc' => enquiryIdEncryption($log->id), 'view' => 'download', 'with_headers' => 1]) }}" target="_blank" {{-- onclick="onClickDownload('{{ $log->company . '-' . $log->enquiry_id . '-' . $log->id }}', '{{ json_encode($log->request) }}' , '{{ json_encode($log->response) }}' , '{{ json_encode($log->headers) }}' , '{{ $log->endpoint_url }}' )" --}}>
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
@endsection('content')

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