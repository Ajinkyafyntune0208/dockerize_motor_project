@extends('admin_lte.layout.app', ['activePage' => 'mongodb', 'titlePage' => __('Dashboard Mongo Logs')])
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

                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label>Enquiry Id</label>
                                    <input id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" class="form-control" placeholder="Enquiry Id" required>
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
@if(request()->filled('enquiryId') && !empty($logs))
<div class="col-lg-12 grid-margin stretch-card">
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
                            <td>{{ $log->transaction_stage }}</td>
                            <td>
                                <a class="btn btn-primary" title='View Request' href="{{ url('admin/mongodb/final', $log->_id) }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </td>
                            <td>{{ date('d-M-Y h:i:s A', strtotime($log->updated_at)) }}</td>
                            <td>{{ date('d-M-Y h:i:s A', strtotime($log->created_at)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
@endsection('content')

@push('scripts')
<script>
    $(document).ready(function() {
        $('#kafka-logs-table').DataTable();
    });
</script>
@endpush