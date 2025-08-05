@extends('layout.app', ['activePage' => 'Dashboard Mongo Logs', 'titlePage' => __('Dashboard Mongo Logs')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">

                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="card-body">
                        <h4 class="card-title">Dashboard Mongo Logs</h4>
                        <p class="card-description"></p>

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

                            <div class="col-md-5">
                                <div class="form-group">
                                    <label class="active required" for="enquiryId">Enquiry Id</label>
                                    <input id="enquiryId" name="enquiryId" type="text"
                                        value="{{ old('enquiryId', request()->enquiryId ?? null) }}" class="form-control"
                                        placeholder="Enquiry Id" required>
                                </div>

                                <button type="submit" class="btn btn-outline-primary">
                                    Search <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
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
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#kafka-logs-table').DataTable();
        });
    </script>
@endpush
