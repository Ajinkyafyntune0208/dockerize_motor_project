@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <form action="{{ route('admin.logs.index') }}" method="GET">
        <!-- @csrf -->
        <div class="row mb-3">
            <div class="col-sm-2">
                <div class="input-field">
                    <label class="active" for="enquiryId">Enquiry Id</label>
                    <input id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId) }}" class="form-control" placeholder="Enquiry Id">
                </div>
            </div>
            <div class="input-field col-sm-2">
                <label>Company</label>
                <select name="company" class="form-control">
                    <option value="">Show All</option>
                    @foreach ($companies as $company)
                    <option {{ old('company', request()->company) == $company ? 'selected' : '' }} value="{{ $company }}">{{strtoupper(str_replace('_',' ', $company)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-field col-sm-2">
                <label>Section</label>
                <select name="section" class="form-control">
                    <option value="">Show All</option>
                    @foreach ($sections as $section)
                    <option {{ old('section', request()->section) == $section ? 'selected' : '' }} value="{{ $section }}">{{ $section }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-field col-sm-2">
                <label>Transaction Type</label>
                <select name="transaction_type" class="form-control">
                    <option value="">Show All</option>
                    <option {{ old('transaction_type', request()->transaction_type) == 'proposal' ? 'selected' : '' }} value="proposal">Proposal</option>
                    <option {{ old('transaction_type', request()->transaction_type) == 'quote' ? 'selected' : '' }} value="quote">Quote</option>
                </select>
            </div>
            <div class="input-field col-sm-2">
                <label for="last_name">Choose Date</label>
                <input type="date" name="date" value="{{ old('date', request()->date) }}" class="form-control">
            </div>
            <div class="input-field col-sm-1">
                <label>View Type</label>
                <select name="view_type" class="form-control">
                    <option {{ old('view_type', request()->view_type) == 'view' ? 'selected' : '' }} value="view">View</option>
                    <option {{ old('view_type', request()->view_type) == 'excel' ? 'selected' : '' }} value="excel">Excel</option>
                </select>
            </div>
            <div class="col-sm-1 input-field align-items-center pt-auto">
                <button type="submit" class="btn btn-outline-primary" style="margin-top: 30px;"><i class="fa fa-search"></i> Search</button>
            </div>
        </div>
    </form>
    <section class="mb-4">
        @if (!empty($logs))
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Company Name</th>
                                <th scope="col">Section</th>
                                <th scope="col">Product Name</th>
                                <th scope="col">Method Name</th>
                                <th scope="col">Transaction Type</th>
                                <th scope="col">Created At</th>
                                <th scope="col" class="text-right">View Request Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                            <tr>
                                <td scope="row">{{ $loop->iteration }}</td>
                                <td>{{strtoupper(str_replace('_',' ', $log->company)) }}</td>
                                <td>{{ $log->section }}</td>
                                <td>{{ $log->product }}</td>
                                <td>{{ $log->method_name }}</td>
                                <td>{{ $log->transaction_type ?? "N/A" }}</td>
                                <td>{{ Carbon\Carbon::parse($log->start_time)->format('d-M-Y H:i:s A') }}</td>
                                <td class="text-right">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.logs.show', $log) }}" target="_blank" class="btn btn-primary"><i class="fa fa-eye"></i></a>
                                        <button type="button" data-toggle="modal" data-target="#modal-{{$loop->iteration}}" class="btn btn-info"><i class="fa fa-info-circle"></i></button>
                                        <button type="button" class="btn btn-success" onclick="onClickDownload('{{ $log->company . '-'. $log->enquiry_id . '-' . $log->id }}', '{{json_encode($log->request)}}', '{{json_encode($log->response)}}','{{($log->endpoint_url)}}')"><i class="fa fa-arrow-circle-down"></i></button>
                                    </div>
                                    <!-- <a class="btn-floating waves-effect waves-light btn modal-trigger" href="#modal-{{$loop->iteration}}"><i class="">info_outline</i></a> -->
                                    <!-- <button class="btn-floating waves-effect waves-light btn " onclick="onClickDownload('dwn-btn-{{$loop->iteration}}', '{{json_encode($log->request)}}', '{{json_encode($log->response)}}','{{($log->endpoint_url)}}')" id="dwn-btn-{{$loop->iteration}}"><i class="material-icons">download</i></button> -->
                                </td>
                            </tr>
                            <!-- Button trigger modal -->
                            @include('components.requestResponseModel',['log' => $log])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="justify-content-end">
                    @if(!$logs->isEmpty())
                    {{ $logs->links() }}
                    @endif
                </div>
            </div>
        </div>
        @else
        <p>No Records or search for records</p>
        @endif
    </section>
</main>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
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