@extends('layout.app', ['activePage' => 'policy-report', 'titlePage' => __('Policy Report')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Policy Report</h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <div class="form-group">
                        <form action="" method="get">
                            <div class="row">
                                @can('report.broker_name.show')
                                <div class="col-md-12">
                                    <div class="row">
                                        <!-- <div class="col-md-4">
                                            <div class="form-group w-100">
                                                <label>Broker Name</label>
                                                <select name="broker_url" id="broker_url" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                                    <option value="">Nothing selected</option>
                                                    @foreach($borker_details as $borker_detail)
                                                    <option {{ old('broker_url', request()->broker_url) == $borker_detail->backend_url ? 'selected' : '' }} value="{{ $borker_detail->backend_url }}">{{ $borker_detail->name . ' - ' . $borker_detail->environment }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div> -->
                                        <!-- <div class="col-md-4">
                                            <div class="form-group w-100">
                                                <label>View Type</label>
                                                <select name="view" id="view" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                                    <option value="">Nothing selected</option>
                                                    <option {{ old('broker_url', request()->view) == 'view' ? 'selected' : '' }} value="view">View</option>
                                                    <option {{ old('broker_url', request()->view) == 'excel' ? 'selected' : '' }} value="excel">Excel</option>
                                                    {{-- @foreach($borker_details as $borker_detail) --}}
                                                    {{-- <option {{ old('broker_url', request()->broker_url) == $borker_detail->backend_url ? 'selected' : '' }} value="{{ $borker_detail->backend_url }}">{{ $borker_detail->name . ' - ' . $borker_detail->environment }}</option> --}}
                                                    {{-- @endforeach --}}
                                                </select>
                                            </div>
                                        </div> -->
                                    </div>
                                   
                                </div>
                                @endcan
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <Label>From Date</Label>
                                        <input type="text" name="from" value="{{ old('from', request()->from) }}" required id="" class="datepickers form-control" placeholder="From" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <Label>To Date</Label>
                                        <input type="text" name="to" value="{{ old('to', request()->to ) }}" required id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                                    </div>
                                </div>
                                {{-- <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Transaction Stage</label>
                                        <select name="transaction_stage[]" multiple data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            <option {{ in_array('Quote - Buy Now', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Quote - Buy Now</option>
                                            <option {{ in_array('Proposal Drafted', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Proposal Drafted</option>
                                            <option {{ in_array('Proposal Accepted', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Proposal Accepted</option>
                                            <option {{ in_array('Policy Issued', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Policy Issued</option>
                                            <option {{ in_array('Payment Failed', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Payment Failed</option>
                                            <option {{ in_array('Policy Issued, but pdf not generated', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Policy Issued, but pdf not generated</option>
                                            <option {{ in_array('Inspection Pending', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Inspection Pending</option>
                                            <option {{ in_array('Payment Initiated', (request()->transaction_stage ?? [])) ? 'selected' : '' }}>Payment Initiated</option>
                                        </select>
                                    </div>
                                </div> --}}
                                {{-- <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Product Name</label>
                                        <select name="product_id" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            @foreach($master_product_sub_types as $key => $master_product_sub_type)
                                            <option {{ $master_product_sub_type->product_sub_type_id == request()->product_id ? 'selected' : '' }} value="{{ $master_product_sub_type->product_sub_type_id }}">{{ $master_product_sub_type->product_sub_type_code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div> --}}
                                {{-- <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Seller Type</label>
                                        <select name="seller_type" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            <option {{ request()->seller_type == 'E' ? 'selected' : '' }}>E</option>
                                            <option {{ request()->seller_type == 'P' ? 'selected' : '' }}>P</option>
                                            <option {{ request()->seller_type == 'U' ? 'selected' : '' }}>U</option>
                                        </select>
                                    </div>
                                </div> --}}
                                <div class="col-md-2">
                                    <div class="d-flex align-items-center h-100">
                                        <input type="submit" class="btn btn-outline-info btn-sm w-100">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    @if ( ! empty($reports))
                    <div class="table-responsive">
                        <table class="table table-striped" id="policy_reports">
                            <thead>
                                <tr>
                                    <th scope="col">Batch Id</th>
                                    <th scope="col">Processed</th>
                                    <th scope="col">Processing</th>
                                    <th scope="col">Success</th>
                                    <th scope="col">Failed after max attempts</th>
                                    <th scope="col">Pending</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reports as $key => $report)
                                <tr>
                                    <td>{{ $key }}</td>
                                    <td>{{ $report['processed'] }}</td>
                                    <td>{{ $report['processing'] }}</td>
                                    <td>{{ $report['success'] }}</td>
                                    <td>{{ $report['failure'] }}</td>
                                    <td>{{ $report['pending'] }}</td>
                                    <td>{{ $report['total'] }}</td>
                                    <td>
                                        <form action="{{ url('admin/embedded-scrub-excel') }}" method="get">
                                            <input type="hidden" name="from" value="{{ old('from', request()->from) }}">
                                            <input type="hidden" name="to" value="{{ old('to', request()->to) }}">
                                            <input type="hidden" name="batch_id" value="{{ $key }}">
                                            <button type="submit" class="btn btn-md btn-success"><i class="fa fa-download"></i></button>
                                        </form>                                       
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="uploadPolicy" tabindex="-1" aria-labelledby="uploadPolicyLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.report.store') }}" method="post" enctype="multipart/form-data" name="uploadPolicyForm">@csrf @method('POST')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPolicyLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="enquiry_id">
                    <div class="form-group">
                        <label for="">Policy No.</label>
                        <input type="text" placeholder="Policy No." class="form-control" name="policy_no" required>
                   <input type="hidden" id="brokerurl" name="brokerurl" >
                    </div>
                    <div class="form-group">
                        <label for="">Status</label>
                        <select name="" class="selectpicker w-100" data-style="btn btn-primary">
                            <option value="">Select Any One</option>
                            <option value="">Success</option>
                            <option value="">Failure</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="">Policy PDF</label>
                        <input type="file" class="btn btn-primary w-100" name="policy_pdf" required accept="application/pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check" aria-hidden="true"></i> Save</button>
                </div>
        </form>
    </div>
</div>
</div>
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

        /* $(document).on('click', '.uploadPolicy', function() {
            $('#uploadPolicyLabel').text(' Policy Upload - ' + $(this).attr('data-enquiry-id'));
            // alert($(this).attr('data-enquiry-id'));
            $('#brokerurl').val($('#broker_url').val());
            $('form[name="uploadPolicyForm"]  input[name="enquiry_id"]').val($(this).attr('data-enquiry-id'));
        }); */
    })
</script>
@endpush
