@extends('admin_lte.layout.app', ['activePage' => 'logs', 'titlePage' => __('Vahan Service Logs')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="" method="GET">
            @if (session('error'))
            <div class="alert alert-danger mt-3 py-1">
                {{ session('error') }}
            </div>
            @endif
            <!-- @csrf -->
            <div class="row mb-3">
                <div class="col-sm-4">
                    <div class="form-group" >
                        <label class='active required'>Find Using</label>
                        <select name="type" data-actions-box="true" class="select2 form-control w-100" data-live-search="true" id="findUsing" required>
                            {{-- <option value="">Select</option> --}}
                            <option {{ old('type', request()->type) === 'enquiryid' ? 'selected' : '' }}
                                value="enquiryid">Equiry Id</option>
                            <option {{ old('type', request()->type) === 'rcNumber' ? 'selected' : '' }}
                                value="rcNumber">RC Number</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Transaction Type</label>
                        <select data-actions-box="true" class="select2 form-control w-100" data-live-search="true" name="transaction_type">
                            <option value="">Show All</option>
                            <option value="proposal"
                                {{ old('transaction_type', request()->transaction_type) == 'proposal' ? 'selected' : '' }}>
                                Proposal</option>
                            <option value="quote"
                                {{ old('transaction_type', request()->transaction_type) == 'quote' ? 'selected' : '' }}>
                                Quote</option>
                        </select>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group" id="enqDetails">
                        <label class="active required" for="userInput">Enquiry Id/ RC Number</label>
                        <input class="form-control" id="enquiryID" name="userInput" type="text"
                            value="{{ old('userInput', request()->userInput ?? null) }}"
                            placeholder="Enquiry ID/ RC Number" required>
                    </div>
                    <div class="form-group">
                        <label>View Type</label>
                        <select data-actions-box="true" class="select2 form-control w-100" data-live-search="true" name="view_type">
                            <option value="view"
                                {{ old('view_type', request()->view_type) == 'view' ? 'selected' : '' }}>
                                View</option>
                            {{-- <option value="excel"
                                {{ old('view_type', request()->view_type) == 'excel' ? 'selected' : '' }}>
                                Excel</option> --}}
                        </select>
                    </div>
                </div>

                <div class="col-sm-4 text-right">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="required d-flex justify-content-start">From Date</label>
                                <input type="text" name="from_date"   value="{{ old('from_date', request()->from_date) }}"
                                required id="" class="datepickers form-control" placeholder="From" autocomplete="off">                                            </div>                                            <div class="col-md-6">
                                <label class="required d-flex justify-content-start">To Date</label>
                                <input type="text" name="to_date" value="{{ old('to_date', request()->to_date) }}" required id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit" style="margin-top: 30px;"><i
                                class="fa fa-search"></i> Search</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@if (!empty($vahan_service_logs))
<div class="card">
    <div class="card-body">
        <table class="table-striped table">
            <thead>
                <tr>
                    <th class="text-right" scope="col"></th>
                    <th scope="col">#</th>
                    <th scope="col">Vehicle Reg No.</th>
                    <th scope="col">Request</th>
                    <th scope="col">Response</th>
                    <th scope="col">Transaction Type</th>
                    <th scope="col">Created At</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vahan_service_logs as $log)
                        <td class="text-right">
                            <div class="btn-group" role="group">
                                @can('vahan_service_logs.show')
                                    <a class="btn btn-sm btn-primary"
                                        href="{{ route('admin.vahan-service-logs.show', $log) }}"
                                        target="_blank"><i class="fa fa-eye"></i></a>
                                    <a class="btn btn-sm btn-success"
                                        href="{{ url('admin/vahan-service-logs/'. $log->id . '?action=download')}}"
                                        target="_blank">
                                        <i class="fa fa-arrow-circle-down"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td>{{ $log->vehicle_reg_no }}</td>
                        <td>{{ $log->request }}</td>
                        <td>{{ $log->response }}</td>
                        <td>{{ $log->stage ?? 'N/A' }}</td>
                        <td>{{ Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s A') }}
                        </td>

                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="float-end mt-1">

            {{ $vahan_service_logs->links() }}
        </div>

    </div>
</div>
@else
<p>
    No Records or search for records
</p>
@endif

@endsection
@section('scripts')
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
        // $(function () {
        //     $("#findUsing").change(function () {
        //         if ($(this).val() == "enquiryid") {
        //             $("#enqDetails").show();
        //         } else {
        //             $("#enqDetails").hide();
        //         }
        //     });
        //     $("#findUsing").change(function () {
        //         if ($(this).val() == "rcNumber") {
        //             $("#rcNumDetails").show();
        //         } else {
        //             $("#rcNumDetails").hide();
        //         }
        //     });
        // });
    </script>
@endsection
