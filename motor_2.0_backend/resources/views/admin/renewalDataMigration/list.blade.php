@extends('layout.app', ['activePage' => 'renewal-data-migration', 'titlePage' => __('Renewal Data Migration Report')])
@section('content')
    <!-- partial -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Renewal Data Migration Report</h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div class="form-group">
                            <form action="" method="get">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>Enquiry ID</Label>
                                            <input type="text" name="enquiryId" value="{{ old('enquiryId', request()->enquiryId) }}" id="" class="form-control" placeholder="Enquiry ID" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>RC Number</Label>
                                            <input type="text" name="rcNumber" value="{{ old('rcNumber', request()->rcNumber) }}" id="" class="form-control" placeholder="RC Number" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>Policy Number</Label>
                                            <input type="text" name="policyNumber" value="{{ old('policyNumber', request()->policyNumber) }}" id="" class="form-control" placeholder="Policy Number" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>From Date</Label>
                                            <input type="text" name="from" value="{{ old('from', request()->from) }}" id="" class="datepickers form-control" placeholder="From" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>To Date</Label>
                                            <input type="text" name="to" value="{{ old('to', request()->to ) }}" id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>View Type</label>
                                            <select name="viewType" id="viewType" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                                <option value="">Nothing selected</option>
                                                <option {{ old('broker_url', request()->viewType) == 'view' ? 'selected' : '' }} value="view">View</option>
                                                <option {{ old('broker_url', request()->viewType) == 'excel' ? 'selected' : '' }} value="excel">Excel</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center h-100">
                                            <input type="submit" class="btn btn-outline-info btn-sm w-100">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="table-responsive">
                            @if (!$reports->isEmpty())
                            <table class="table table-striped" id="policy_reports">
                                <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col"></th>
                                    <th scope="col">Enquiry ID</th>
                                    <th scope="col">Existing Enquiry ID</th>
                                    <th scope="col">RC Number</th>
                                    <th scope="col">Policy Number</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created At</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($reports as $index => $report)
                                    <tr>
                                        <td>{{ ++$index }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('admin.renewal-data-migration.show', ['renewal_data_migration' => $report->id]) }}"
                                                target="_blank"><i class="fa fa-eye"></i></a>
                                            </a>
                                            <a class="btn btn-sm btn-success"
                                                href="{{ route('admin.renewal-data-migration.download', ['id' => $report->id]) }}"
                                                target="_blank">
                                                <i class="fa fa-arrow-circle-down"></i>
                                            </a>
                                        </td>
                                        <td>{{ !empty($report['user_product_journey_id']) ? customEncrypt($report['user_product_journey_id']) : '-' }}</td>
                                        <td>
                                            {{ !empty($report->user_product_journey->old_journey_id) ? customEncrypt($report->user_product_journey->old_journey_id) : '-' }}
                                        </td>
                                        <td>{{ $report['registration_number'] ?? '-' }}</td>
                                        <td>{{ $report['policy_number'] ?? '-' }}</td>
                                        <td>{{ $report['status'] }}</td>
                                        <td>{{ $report['created_at']}}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @else
                                <p style="text-align: center;">No Records found.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('table').DataTable();
            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });
        })
    </script>
@endpush
