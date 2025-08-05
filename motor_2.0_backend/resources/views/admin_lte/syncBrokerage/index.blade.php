@extends('admin_lte.layout.app', ['activePage' => 'Brokerage-Logs', 'titlePage' => __('Brokerage-Logs')])

@section('content')
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<div class='card card-primary'>
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
               
                <div class="form-group">
                    <form name="{{route('admin.BrokerageLogs.index')}}" id="searchForm">
                        @csrf
                        <div class="row ml-3 mt-4">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="enquiryId">Enquiry ID</Label>
                                    <input id="enquiryId" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" type="text" name="enquiryId" id="enquiryId" class="form-control" placeholder="Enquiry ID" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="configid">Config ID</Label>
                                    <input id="configid" value="{{ old('configid', request()->configid ?? null) }}" type="text" name="configid" id="configid" class="form-control" placeholder="Config ID" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="from">From Date</Label>
                                    <input id="from" type="date" value="{{ old('from', request()->from) }}" required name="from" id="from" class="datepickers form-control" placeholder="From" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="to">To Date</Label>
                                    <input id="to" required type="date" value="{{ old('to', request()->to) }}" name="to" id="to" class="datepickers form-control" placeholder="To" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row ml-3 d-flex justify-content-start">
                            <div class="col-2">
                                <input type="submit" class="btn btn-outline-info btn-sm">
                            </div>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>

</div>
<div class="table-responsive">
    @if (!empty($reports))
    <table id="response_log" class="table table-bordered table-hover" >
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Action</th>
                <th scope="col">Enquiry ID</th>
                <th scope="col">Conf ID</th>
                <th scope="col">Created at</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($reports))
            @foreach($reports as $key => $value)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.BrokerageLogs.show', $value->id) }}" target="_blank"><i
                            class="fa fa-eye"></i>
                    </a>
                </td>
                <td>{{ customEncrypt($value->user_product_journey_id) ?? '-' }}</td>
                <td>{{ $value->retrospective_conf_id ?? '-' }}</td>
                <td>{{ $value->created_at ?? '-' }}</td>
            </tr>
            @endforeach
            @endif
        </tbody>
    </table>
    @else
    <p style="text-align: center;">No Records found.</p>
    @endif
</div>
@endsection('content')

@section('scripts')
<script>
    $('#response_log').DataTable({
            "bPaginate": true,
            "paging": true,
            "bInfo": true
        });
</script>
@endsection