@extends('admin_lte.layout.app', ['activePage' => 'Dashboard Mongo Logs', 'titlePage' => __('Dashboard Mongo Logs')])
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="card">
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-md-5 col-lg-4 form-group">
                    <label class="active required" for="enquiryId">Enquiry Id</label>
                    <input id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                        class="form-control" placeholder="Enquiry Id" required>
                
                </div>
                <div class="col-md-5 col-lg-3 form-group">
                    <label class="active required" for="enquiryId">Type</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="ENCRYPTED" {{old('type', request()->type ?? null) == 'ENCRYPTED' ? 'selected' : ''}}>Encrypted Enquiry Id</option>
                        <option value="NON-ENCRYPTED" {{old('type', request()->type ?? null) == 'NON-ENCRYPTED' ? 'selected' : ''}}>Non Encrypted Enquiry Id</option>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        Search <i class="fa fa-search"></i>
                    </button>
                </div>
            </div>
        
        </form>
    </div>
</div>
@if(request()->filled('enquiryId') && !empty($logs))
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id='kafka-logs-table' class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Stage</th>
                        <th scope="col">Request</th>
                        <th scope="col">Updated At</th>
                        <th scope="col">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td scope="row">{{ $loop->iteration }}</td>
                            <td>{{ $log['transaction_stage'] }}</td>
                            <td>
                                <a class="btn btn-primary" title='View Request' href="{{ url('admin/mongodb/final', $log['_id']) }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </td>
                            <td>{{ !empty($log['updated_at']) ? date('d-M-Y h:i:s A', strtotime($log['updated_at'])) : null }}</td>
                            <td>{{ !empty($log['created_at']) ? date('d-M-Y h:i:s A', strtotime($log['created_at'])) : null }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#kafka-logs-table').DataTable();
        });
    </script>
@endsection
