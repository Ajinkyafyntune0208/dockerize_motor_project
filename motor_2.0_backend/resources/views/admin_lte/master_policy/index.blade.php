@extends('admin_lte.layout.app', ['activePage' => 'master-product', 'titlePage' => __('Master Product')])
@section('content')
<div class="card">
    <div class="card-body">
        <form  action="" method="GET" id="custom-search">
            @csrf
            <input type="hidden" name="per_page" id="per_page" value="">
            <div class="row">

                <div class="col-md-4 form-group">
                    <label for="">Company Name</label>
                    <select name="company_id[]" id="company_id" data-live-search="true" data-actions-box="true" class="select2 form-control w-100">
                    <option value=""  {{ isset(request()->company_id) ? '' : 'selected'}}> Select Company name</option>
                            @foreach($mcomp as $company)
                                <option value="{{ $company->company_id }}"  {{ isset(request()->company_id) && ( in_array($company->company_id,request()->company_id )) ? 'selected' : '' }}>
                                    {{ $company->company_name }}
                                </option>
                            @endforeach
                    </select>
                </div>

                <div class="col-sm-4 form-group">
                    <label class="font-weight-bold" for="bid">Business Type</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="bid" id="bid">
                        <option value=""  {{ isset(request()->bid) ? '' : 'selected'}}> Select Business type</option>
                            <option {{old('bid', request()->bid) == '1' ? 'selected' : '' }} value="1">New Business</option>
                            <option {{old('bid', request()->bid) ==  '2' ? 'selected' : '' }} value="2">Roll over</option>
                            <option {{old('bid', request()->bid) ==  '3'? 'selected' : '' }} value="3">Breakin</option>
                        </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="">Premium Type</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="premium_type" id="premium_type">
                        <option value="" {{ isset(request()->premium_type) ? '' : 'selected'}}> Select Premium Type</option>
                        @foreach ($mpremiums as $mpremium)
                            <option {{old('premium_type', request()->premium_type) == $mpremium->id ? 'selected' : '' }} value="{{$mpremium->id}}">{{$mpremium->premium_type}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="">Product Sub Type</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="product_sub_type" id="product_sub_type">
                        <option value="" {{ isset(request()->product_sub_type) ? '' : 'selected'}}> Select Product Sub Type</option>
                        @foreach ($pstype as $stype)
                            <option {{old('product_sub_type', request()->product_sub_type) == $stype->product_sub_type_id ? 'selected' : '' }} value="{{$stype->product_sub_type_id}}">{{$stype->product_sub_type_id."-".$stype->product_sub_type_code}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="">Good Driver Discount</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="gdd" id="gdd">
                        <option value="" {{ isset(request()->gdd) ? '' : 'selected'}}> Select Good Driver Discount</option>
                        <option {{old('gdd', request()->gdd) == 'Yes' ? 'selected' : '' }} value="Yes">Yes</option>
                        <option {{old('gdd', request()->gdd) == 'No' ? 'selected' : '' }} value="No">No</option>
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="">Product Status</label>
                    <select data-actions-box="true" class="select2 w-100 form-control" data-live-search="true" name="pstatus" id="pstatus">
                        <option value="" {{ isset(request()->pstatus) ? '' : 'selected'}}> Select Status</option>
                        <option {{old('pstatus', request()->pstatus) == '1' ? 'selected' : '' }} value="1">Active</option>
                        <option {{old('pstatus', request()->pstatus) == '0' ? 'selected' : '' }} value="0">Inactive</option>
                    </select>
                </div>

                @if(Session::has('previous_url'))
                    @php
                        session()->forget('previous_url');
                        session(['previous_url' =>URL::full()]);
                        $previous_url = session()->get('previous_url')
                    @endphp
                @else
                    @php
                        session(['previous_url' =>URL::full()]);
                        $previous_url = session()->get('previous_url')
                    @endphp
                @endif
            </div>
            <div class="row justify-content-between align-items-baseline ">
                <div class="col-md-12 mt-3">
                        <button type="submit" id="submit" class="btn btn-primary"><i class="fa fa-search m-1"></i> Search</button>
                        <a  href="#" id="clear" class="btn btn-primary"><i class="fa fa-eraser m-1"></i> Clear Fields</a>
                        @can('master_product.create')
                            <a href="{{ route('admin.master-product.create') }}?previous_url={{urlencode($previous_url) }}" class="btn btn-secondary" style="float:right"> <i class="fa fa-plus-square" aria-hidden="true"></i> Add New Product</a>
                        @endcan
                </div>
                <!-- <div class="col-md-4" style="justify-content: space-evenly;">

                </div> -->
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        @if ($master_policies->isEmpty())
            <div class="alert alert-warning mt-3"> <i class="fa fa-warning"></i> No records found!</div>
        @else
        @php
            $perPageValues = [25,50,75,100];
        @endphp
                <table id="data-table" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th scope="col">Product Key</th>
                            <th>Product ID</th>
                            <th>Comany Name</th>
                            <th>Business Type</th>
                            <th>Product Type</th>
                            <th>Product Identifier</th>
                            <th>Product Name</th>
                            <th>Premium Type</th>
                            <th>Good Driver Discount</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Updated Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($master_policies as $key => $master_policy)
                            <tr>
                                <td>
                                    @can('master_product.edit')
                                        {{-- <a href="#" class="btn btn-sm btn-success" onclick="changestatus(`{{$master_policy->policy_id}}`,`{{$master_policy->business_type}}`, `{{ route('admin.master-policy.update', $master_policy->policy_id) }}`,`{{$master_policy->master_policy_status}}`)"><i class="fa fa-edit"></i></a> --}}
                                        <a class="btn btn-info" href="{{ route('admin.master-product.edit', $master_policy) }}?previous_url={{urlencode($previous_url)}}" title="Edit">
                                            <i class="fa fa-edit" aria-hidden="true"></i>
                                        </a>
                                        {{-- @if (config("DISABLE_MASTER_POLICY_DELETION") != 'Y')
                                        <form method="POST" action="{{ route('admin.master-product.destroy',$master_policy) }}" accept-charset="UTF-8" style="display:inline">
                                            {{ method_field('DELETE') }}
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete Product" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                                        </form>
                                        @endif --}}
                                    @endcan
                                </td>

                                <td scope="col" class="text-center">{{ $master_policy->product_key }}</td>

                                <td>{{ $master_policy->policy_id }}</td>
                                <td>{{ $master_policy->company_name }}</td>
                                <td>{{ ucwords(str_replace(',', ' and ', $master_policy->business_type)) }}</td>
                                <td>{{ $master_policy->product_sub_type_code ?? '' }}</td>
                                <td>{{ $master_policy->master_product->product_identifier ?? 'Not Mentioned' }}</td>
                                <td class="{{ $master_policy->master_product['product_name'] ?? 'text-warning' }}">
                                    {{ $master_policy->master_product['product_name'] ?? 'Not Mentioned' }}
                                </td>
                                <td>{{ $master_policy->premium_type->premium_type ?? 'Not Mentioned' }}</td>
                                <td>{{ $master_policy->good_driver_discount ?? 'Not Mentioned' }}</td>
                                <td>
                                    {{ strtolower($master_policy->master_policy_status) == 'active' ? 'Active' : 'Inactive'}}
                                </td>
                                <td>{{ isset($master_policy->created_date) ? Carbon\Carbon::parse($master_policy->created_date)->format('d-M-Y H:i:s') : 'Not Mentioned' }}</td>
                                <td>{{ isset($master_policy->updated_date) ? Carbon\Carbon::parse($master_policy->updated_date)->format('d-M-Y H:i:s') : 'Not updated' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        @endif
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">

            <p>Total Result found: {{$master_policies->total()}}</p>
            <p>Showing records per page: {{$master_policies->count()}}</p>
            <div>
                @if(!$master_policies->isEmpty())
                    {{ $master_policies->appends(request()->query())->links() }}
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Product Enable/Disable</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" id="policy_update" name="policy_update" method="POST">@csrf @method('PUT')

                <div class="modal-body">
                    <div class="row">

                        <div class="col-md-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="Rollover"
                                    name="business_type[]" value="rollover">
                                <label class="custom-control-label" for="Rollover">Rollover</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="Newbusiness"
                                    name="business_type[]" value="newbusiness">
                                <label class="custom-control-label" for="Newbusiness">New Business</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="Breakin"
                                    name="business_type[]" value="breakin">
                                <label class="custom-control-label" for="Breakin">Breakin</label>
                            </div>
                        </div>
                        <br>
                        <br>
                        <div class="col-md-6">
                            <input class="form-check-input" type="radio" name="status" id="activestatus"
                                value="Active" checked>
                            <label class="form-check-label" for="activestatus">
                                Active
                            </label>

                        </div>
                        <div class="col-md-6">
                            <input class="form-check-input" type="radio" name="status" id="Inactivestatus"
                                value="Inactive">
                            <label class="form-check-label" for="Inactivestatus">
                                Inactive
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            let perpage = $('#per_page_hidden').val();
            if($('#data-table').length){
                $(function () {
                $("#data-table").DataTable({
                    "responsive": false, "lengthChange": true, "autoWidth": false, "bPaginate": false,"scrollX": true,
                "buttons": ["copy", "csv", "excel",{
                extend: 'pdfHtml5',
                orientation : 'landscape',
                pageSize : 'A2',
                },"print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                },
            ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
            });
            }
            $('#per_page_hidden').on('change', function() {
                var selectedValue = $(this).val();
                var news = $('#per_page').val(selectedValue);
                $('#submit').click();
            });
            $('#clear').on('click', function(e) {
                e.preventDefault();
                //$("#custom-search")[0].reset();
                let company_id = $('#company_id');
                company_id.val("");
                company_id.selectpicker('refresh');

                $('#bid').val("").selectpicker('refresh');
                $('#premium_type').val("").selectpicker('refresh');
                $('#product_sub_type').val("").selectpicker('refresh');
                $('#gdd').val("").selectpicker('refresh');
                $('#pstatus').val("").selectpicker('refresh');

            });
            $('.updatestatus').on('change', function() {
                var status = $(this).val();
                var id = $(this).attr('data-id');

                var data = {
                    "_token": "{{ csrf_token() }}",
                    status: status,
                    policy_id: id
                };

                $.ajax({
                    type: "POST",
                    contentType: "application/json",
                    url: "{{ route('admin.masterproduct-statusupdate') }}",
                    data: JSON.stringify(data),
                    success: function (response) {
                        if (response['status'] == true) {
                            console.log(response['status']);
                            if ($('.updatestatus[data-id="' + id + '"]').length) {
                                var element = $('.updatestatus[data-id="' + id + '"]');

                                if (element.val() == 'Active') {
                                    element.removeClass('btn-danger').addClass('btn-success');
                                } else if (element.val() == 'Inactive') {
                                    element.removeClass('btn-success').addClass('btn-danger');
                                }
                            }
                        } else {
                            console.log(response['message']);
                        }

                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });

        });

        function changestatus(id, type, url, status) {
            $('#Newbusiness').prop('checked', false);
            $('#Rollover').prop('checked', false);
            $('#Breakin').prop('checked', false);
            $('#policy_update').prop('action', url);
            $('#exampleModalLabel').text('Product Enable/Disable - ' + id)
            if (type != null) {
                let businesstype = type.split(',');
                businesstype.forEach((value) => {
                    if (value == "newbusiness") {
                        $('#Newbusiness').prop('checked', true);
                    }
                    if (value == "rollover") {
                        $('#Rollover').prop('checked', true);
                    }
                    if (value == "breakin") {
                        $('#Breakin').prop('checked', true);
                    }
                });
                $('#exampleModal').modal('show');

            }
            if (status == "Active") {
                $('#activestatus').prop('checked', true);
            } else {
                $('#Inactivestatus').prop('checked', true);
            }
        }
    </script>
@endsection
