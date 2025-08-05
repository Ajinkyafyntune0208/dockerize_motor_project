@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('OLA Whats App Logs')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Logs</h4>
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
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="active" for="enquiryId">Enquiry Id</label>
                                        <input id="enquiryId" name="enquiryId" type="text"
                                            value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                                            class="form-control" placeholder="Enquiry Id">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="">From Date</label>
                                        <input type="text" name="from_date"
                                            value="{{ old('from_date', request()->from_date ?? '') }}"
                                            class="form-control datepickers">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="">To Date</label>
                                        <input type="text" name="to_date"
                                            value="{{ old('to_date', request()->to_date ?? '') }}"
                                            class="form-control datepickers">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
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
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">
                                            Submit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 grid-margin stretch-card">
                <!-- @ dd(empty($logs)) -->
                @if (!empty($logs))
                    <div class="card">
                        <div class="card-body">
                            {{-- <h4 class="card-title">Logs</h4>
                            <p class="card-description"></p> --}}
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            {{-- <th scope="col" class="text-right">View Request Response</th> --}}
                                            <th scope="col">Enquiry ID</th>
                                            <th scope="col">Request ID</th>
                                            <th scope="col">Mobile No</th>
                                            <th scope="col">Template Name</th>
                                            <th scope="col">Parameters</th>
                                            <th scope="col">Sent Time</th>
                                            <th scope="col">Delivered Time</th>
                                            <th scope="col">Seen Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        @foreach ($logs as $key => $value)
                                            <tr>
                                        

                                        {{-- foreach ($logs as $key => $value) { --}}
                                            {{-- // dd($value, $value->request, $value->response, $value->status_data); --}}
                        
                                            @php $send_time = $delivered_time = $read_time = null; @endphp
                                            
                                            @foreach ($value->status_data as $key => $status_data) 
                                                @if (isset($status_data->request['status']) && $status_data->request['status'] == "sent" )
                                                @php $send_time = $status_data->created_at @endphp
                                                @endif
                                                @if (isset($status_data->request['status']) && $status_data->request['status'] == "delivered" )
                                                @php $delivered_time = $status_data->created_at @endphp
                                                @endif
                                                
                                                @if (isset($status_data->request['status']) && $status_data->request['status'] == "read" )
                                                @php $read_time = $status_data->created_at @endphp
                                                @endif
                                                
                                                @endforeach
                        
                                            {{-- $reports[] = [
                                                $value->enquiry_id,
                                                $value->request_id,
                                                $value->mobile_no,
                                                $value->request['template_name'] ?? null,
                                                $value->request['params'] ?? null,
                                                $send_time,
                                                $delivered_time,
                                                $read_time,
                                            ]; --}}
                                        {{-- @endforeach --}}
                                                {{-- <td class="text-right">
                                                    <div class="btn-group" role="group">
                                                        @can('log.show') --}}
                                                            {{-- <a href="{{ route('admin.log.show', ['log' => $log, 'transaction_type' => $log->transaction_type]) }}"
                                                                target="_blank" class="btn btn-sm btn-primary"><i
                                                                    class="fa fa-eye"></i></a> --}}
                                                            <!-- <button type="button" data-toggle="modal" data-target="#modal-{{ $loop->iteration }}" class="btn btn-info"><i class="fa fa-info-circle"></i></button> -->
                                                            {{-- <button type="button" class="btn btn-sm btn-success"
                                                                onclick="onClickDownload('{{ $log->company . '-' . $log->enquiry_id . '-' . $log->id }}', '{{ json_encode($log->request) }}', '{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')"><i
                                                                    class="fa fa-arrow-circle-down"></i></button> --}}
                                                        {{-- @endcan
                                                    </div> --}}
                                                    {{-- <!-- <a class="btn-floating waves-effect waves-light btn modal-trigger" href="#modal-{{ $loop->iteration }}"><i class="">info_outline</i></a> --> --}}
                                                    {{-- <!-- <button class="btn-floating waves-effect waves-light btn " onclick="onClickDownload('dwn-btn-{{ $loop->iteration }}', '{{ json_encode($log->request) }}', '{{ json_encode($log->response) }}','{{ $log->endpoint_url }}')" id="dwn-btn-{{ $loop->iteration }}"><i class="material-icons">download</i></button> --> --}}
                                                </td>
                                                <td>{{ $value->enquiry_id }}</td>
                                                <td>{{ $value->request_id }}</td>
                                                <td>{{ $value->mobile_no }}</td>
                                                <td>{{ $value->request['template_name'] ?? null }}</td>
                                                <td>{{  $value->request['params'] ?? null, }}</td>
                                                <td>{{ $send_time }}</td>
                                                <td>{{ $delivered_time }}</td>
                                                <td>{{ $read_time }}</td>
                                                <td>{{ '' }}
                                                </td>
                                            </tr>
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
            </div>
            </div>
        </div>
        <!-- content-wrapper ends -->
    @endsection
    @push('scripts')
        <script>
            $(document).ready(function() {
                $('.datepickers').datepicker({
                    todayBtn: "linked",
                    autoclose: true,
                    clearBtn: true,
                    todayHighlight: true,
                    toggleActive: true,
                    format: "yyyy-mm-dd"
                });
            });
        </script>
    @endpush
