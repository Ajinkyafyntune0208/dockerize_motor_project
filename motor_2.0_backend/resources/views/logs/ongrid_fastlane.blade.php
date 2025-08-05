@extends('layout.app' , ['activePage' => 'logs', 'titlePage' => __('Ongrid Fastlane Logs')])
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
                        <!-- @csrf -->
                        <div class="row mb-3">
                            <div class="col-sm-9">
                                {{-- <div class="form-group">
                                    <label class="active" for="registration_no">Vehicle Registation No.</label>
                                    <input id="registration_no" name="registration_no" type="text" value="{{ old('registration_no', request()->registration_no) }}" class="form-control" placeholder="Enquiry Id">
                                </div> --}}
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label for="">From Date</label>
                                            <input type="text" name="from_date" value="{{ old('from_date', request()->from_date) }}" required id=""
                                         class="datepickers form-control" placeholder="From" autocomplete="off">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="">To Date</label>
                                            <input type="text" name="to_date" value="{{ old('to_date', request()->to_date) }}"
                                             required id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                                                
                                        </div>
                                        <div class="col-md-3">
                                            <label>View Type</label>
                                            <select name="status" class="form-control">
                                                <option
                                                {{ old('status', request()->status) == 'All' ? 'selected' : '' }}
                                                value="All">All</option>
                                                <option
                                                    {{ old('status', request()->status) == 'Success' ? 'selected' : '' }}
                                                    value="Success">Success</option>
                                                <option
                                                    {{ old('status', request()->status) == 'Failed' ? 'selected' : '' }}
                                                    value="Failed">Failed</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label>View Type</label>
                                            <select name="view_type" class="form-control">
                                                <option
                                                    {{ old('view_type', request()->view_type) == 'view' ? 'selected' : '' }}
                                                    value="view">View</option>
                                                <option
                                                    {{ old('view_type', request()->view_type) == 'excel' ? 'selected' : '' }}
                                                    value="excel">Excel</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3 text-right">
                                <button type="submit" class="btn btn-sm btn-outline-primary" style="margin-top: 30px;"><i class="fa fa-search"></i> Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-12 grid-margin stretch-card">
            <!-- @ dd(($logs)) -->
            @if (!empty($logs))
            <div class="card">
                <div class="card-body">
                    {{--<h4 class="card-title">Logs</h4>
                            <p class="card-description"></p>--}}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                @if(config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'ongrid')
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Vehicle Registration No.</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col" class="text-right">View Request Response</th>
                                </tr>
                                @else
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Vehicle Registration No   faf.</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col" class="text-right">View Request Response</th>
                                </tr>
                                @endif
                            </thead>
                            <tbody>
                                @if(config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'ongrid')
                                @foreach ($logs as $log)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>{{ $log->vehicle_reg_no }}</td>
                                    <td>{{ Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s A') }}</td>
                                    <td class="text-right">
                                        <div class="btn-group" role="group">
                                            <!-- @ can('log.show') -->
                                            {{-- <a href="{{ route('admin.log.show', $log->id) }}" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i></a> --}}
                                            <button type="button" class="btn btn-sm btn-success" onclick="onClickDownload('{{ $log->vehicle_reg_no }}', '{{json_encode($log->vehicle_reg_no)}}', '{{json_encode($log->vehicle_details)}}')"><i class="fa fa-arrow-circle-down"></i></button>
                                            <!-- @ endcan -->
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                @foreach ($logs as $log)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>{{ $log->request }}</td>
                                    <td>{{ Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s A') }}</td>
                                    <td class="text-right">
                                        <div class="btn-group" role="group">
                                            <!-- @ can('log.show') -->
                                            <a href="{{ route('admin.log.show', $log->id) }}" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i></a>
                                            <button type="button" class="btn btn-sm btn-success" onclick="onClickDownload('{{ $log->request }}', '{{json_encode($log->request)}}', '{{json_encode($log->response)}}')"><i class="fa fa-arrow-circle-down"></i></button>
                                            <!-- @ endcan -->
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
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
<!-- content-wrapper ends -->
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

        function onClickDownload(filename, request, response, url) {
            let text = `\nRequest : \n${request}\n\n\nResponse : \n${response}`;
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