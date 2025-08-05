@extends('admin_lte.layout.app', ['activePage' => 'commission-api-logs', 'titlePage' => __('Commission Api Logs')])

@section('content')

<div class="card card-primary">
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                    <label for="enquiryId">Enquiry Id <span class="text-danger"> *</span></label>
                    <input class="form-control" id="enquiryId" name="enquiryId" type="text"
                        value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" required
                        aria-required="true">
                    @error('message')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Search</button>
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
                        <th scope="col">Transaction Type</th>
                        <th scope="col">Commission Type</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td class="text-right">
                            <a class="btn btn-sm btn-primary" class="btn btn-primary float-end btn-sm"
                            href="{{route('admin.commission-api-logs.show', ['commission_api_log' => $log->id])}}" target="_blank">
                                <i class="fa fa-eye"></i></a>
                        </td>
                        <td>{{$log->transaction_type}}</td>
                        <td>{{$log->type}}</td>
                        <td>{{date('d-M-Y h:i:s A', strtotime($log->date))}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection