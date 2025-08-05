@extends('layout.app', ['activePage' => 'IC_Configurator', 'titlePage' => __('IC Conf')])

@section('content')
    <script src="{{ asset('js/ic_conf/jquery.min.js') }}"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <!-- partial -->
    <style>
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    </style>
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        @if ($errors->any())
                            <div id="errorAlert" class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <h5 class="card-title">Product</h5>
                        <button id="downloadExcelBtn" class="btn btn-primary">Download Excel</button>
                        <div class="form-group">
                            <form action="" method="get" id="policyReportForm"> <!-- Added an id to the form -->
                                @csrf
                                <div class="row">
                                    @can('report.broker_name.show')
                                        <div class="col-md-12">
                                            <div class="row">
                                                <div class="col-md-5 col-lg-3">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Product Name</label>
                                                        <!-- <select name="broker_url" id="broker_url" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            {{-- @foreach ($product_dropdown as $companyItem)
                <option value="{{ $companyItem->product_sub_type_id }}">{{ $companyItem->product_sub_type_name }}</option>
                @endforeach --}}
                                                        </select> -->
                                                        <select name="broker_url" id="broker_url"
                                                            data-style="btn-sm btn-primary" class="selectpicker w-100"
                                                            data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            @foreach ($product_dropdown as $companyItem)
                                                                <option value="{{ $companyItem->product_sub_type_id }}"
                                                                    {{ old('product_sub_type') == $companyItem->product_sub_type_id ? 'selected' : '' }}>
                                                                    {{ $companyItem->product_sub_type_name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-5 col-lg-3">
                                                    <div class="form-group w-100">
                                                        <label class = "required">IC Name</label>
                                                        <select name="view" id="view" data-style="btn-sm btn-primary"
                                                            class="selectpicker w-100" data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            @foreach ($alias as $companyItem)
                                                                <!-- <option value="{{ $companyItem->insurance_company_id }}">{{ $companyItem->company_name }}</option> -->
                                                                '<option value="{{ $companyItem->insurance_company_id }}"
                                                                    {{ old('company_alias') == $companyItem->insurance_company_id ? 'selected' : '' }}>
                                                                    {{ $companyItem->company_name }}</option>'
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-5 col-lg-3">
                                                    <div class="form-group w-100">
                                                        <label class = "required" for="businessType">Business Type</label>
                                                        <select name="businessType" id="businessType" required data-style="btn-sm btn-primary"
                                                            class="selectpicker w-100" data-live-search="true">
                                                            <option value="" selected disabled>Nothing selected</option>
                                                            @foreach ($businessType as $item)
                                                            <option value="{{$item}}">{{$item}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="d-flex align-items-center h-100">
                                                        <button type="submit" class="btn btn-outline-info btn-sm w-100">Search</button>
                                                    </div>
                                                </div>
                                                {{-- master company --}}
                                                <!-- <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label>Premium Type</label>
                                                        <select name="premium_type[]" id="premium_type" data-style="btn-sm btn-primary" class="selectpicker w-100" data-live-search="true" multiple>
                                                            <option value="">Nothing selected</option>
                                                            {{-- @foreach ($company as $premium_type)
        <option value="{{ $premium_type->id }}">{{ $premium_type->premium_type }}</option>
        @endforeach --}}
                                                        </select>
                                                    </div>
                                                </div> -->
                                            </div>
                                        </div>
                                    @endcan
                                </div>
                            </form>
                        </div>
                        <form action="{{ url('admin/ic-config/update-product') }}" method="POST" id="saveForm">
                            <!-- Added an id to the form -->
                            @csrf
                            <input type="hidden" id="company_alias_id" name="company_alias" value="">
                            <input type="hidden" id="product_sub_type_id" name="product_sub_type" value="">

                            <div class="table-responsive" id = "table-data">
                                @if (session('status'))
                                    <div id="alertMessage" class="alert alert-{{ session('class') }}">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                <table id='policy_reports'>

                                </table>

                            </div>
                            <br>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center h-100">
                                    <button type="submit" id="form_submit" hidden
                                        class="btn btn-outline-info btn-sm w-100">Save</button>
                                    <!-- Changed to type="submit" -->
                                </div>
                            </div>
                            <br>
                            <div class="d-flex flex-row">
                                <div class="d-flex align-items-left h-100 col-sm-9">
                                    <button type="button" id="nextBtn" class="btn btn-success mx-2"
                                        style="border-radius: 40px"><- IC Credential</button>
                                </div>
                                <div class="d-flex justify-content-end align-items-center h-100 col-sm-3">
                                    <button type="button" id="newBtn" class="btn btn-success"
                                        style="border-radius: 40px">IC Miscellaneous -></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
var dataTableInitialized = false;
var gcvCarrierTypeHeaderAdded = false;
var table = '';

function initializeDataTable() {
    if (!dataTableInitialized) {
        $('#policy_reports').DataTable({
            "initComplete": function() {
                var notApplyFilterOnColumn = [5, 6, 7, 8, 9, 10];
                var showFilterBox = 'afterHeading';

                $('.gtp-dt-filter-row').remove();
                var theadSecondRow = '<tr class="gtp-dt-filter-row">';

                $(this.api().columns().header()).each(function(index) {
                    if (notApplyFilterOnColumn.indexOf(index) === -1) {
                        theadSecondRow += '<td class="gtp-dt-select-filter-' + index + '"></td>';
                    } else {
                        theadSecondRow += '<td></td>';
                    }
                });

                theadSecondRow += '</tr>';

                if (showFilterBox === 'beforeHeading') {
                    $(this.api().table().header()).prepend(theadSecondRow);
                } else if (showFilterBox === 'afterHeading') {
                    $(theadSecondRow).insertAfter($(this.api().table().header()));
                }

                this.api().columns().every(function(index) {
                    var column = this;
                    if (notApplyFilterOnColumn.indexOf(index) === -1) {
                        var select = $('<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true"><option value="">Select</option></select>')
                            .on('change', function() {
                                var val = $.fn.dataTable.util.escapeRegex($(this).val());

                                column
                                    .search(val ? '^' + val + '$' : '', true, false)
                                    .draw();
                            });

                        column.data().unique().sort().each(function(d, j) {
                            let dataValue = '';
                            let dataText = '';

                            // Create a temporary DOM element to extract the text
                            const tempElement = $('<div>').html(d);

                            if (tempElement.find('input').length > 0) {
                                dataText = tempElement.find('input').parent().text();
                                dataValue = tempElement.find('input').val();
                            } else {
                                dataText = tempElement.text();
                                dataValue = tempElement.text();
                            }

                            dataText = dataText.replace(/<[^>]*>?/gm, '');

                            select.append('<option value="' + dataValue + '">' + dataText + '</option>');
                        });

                        $('td.gtp-dt-select-filter-' + index).html(select);
                        $('.selectpicker').selectpicker();
                    }
                });
            },
            "paging": false,
            "ordering": false
        });
        dataTableInitialized = true;
    } else {
        console.warn("DataTable is already initialized on this table.");
    }
}

            var GcvProduct = ['DUMPER/TIPPER', 'TRUCK', 'PICK UP/DELIVERY/REFRIGERATED VAN', 'TRACTOR',
                'TANKER/BULKER', 'GCV TRACTOR'
            ];
            var gcvCarrierTypeHeaderAdded = false;
            $('#policyReportForm').submit(function(event) {

                event.preventDefault();
                var productSubTypeName = $('select[name="broker_url"] option:selected').text().trim();
                var productSubTypeId = $('#broker_url').val();
                var insuranceCompanyId = $('#view').val();
                let businessType = $('#businessType').val();
                isProductSubTypeIncluded = GcvProduct.includes(productSubTypeName.toUpperCase());

                var isValid = false;
                var errorMsg = "";
                if (productSubTypeId === "") {
                    isValid = true;
                    errorMsg += 'Please select an item in Product Name.\n';
                }
                if(insuranceCompanyId === ""){
                    var isValid = true;
                    errorMsg += 'Please select an item in IC Name.\n';
                }
                if(isValid){
                    alert(errorMsg);
                    return false;
                }

                // if (isProductSubTypeIncluded) {
                //     $('#ownerTypeHeader').after('<th scope="col" id = "gcvCarrierTypeHeader">GCV Carrier Type</th>');
                // } else{
                //     $('#gcvCarrierTypeHeader').remove();
                //     console.log(21);
                // }
                    if (isProductSubTypeIncluded && !gcvCarrierTypeHeaderAdded) {
                     $('#ownerTypeHeader').after('<th scope="col" id="gcvCarrierTypeHeader">GCV Carrier Type</th>');
                     gcvCarrierTypeHeaderAdded = true;
                    } else if (!isProductSubTypeIncluded && gcvCarrierTypeHeaderAdded) {
                        $('#gcvCarrierTypeHeader').remove();
                        gcvCarrierTypeHeaderAdded = false;
                    }
                $.ajax({
                    url: '{{ route('ic-config.ProductList') }}',
                    method: 'GET',
                    data: {
                        "businessType" : businessType,
                        "product_sub_type_id": productSubTypeId,
                        "insurance_company_id": insuranceCompanyId,
                        // "premium_type_id": premiumTypeIds // Include premium type IDs as an array
                    },
                    success: function(response) {
                        // Clear existing table rows
                        if ($.fn.DataTable.isDataTable('#policy_reports')) {
                            $('#policy_reports').DataTable().destroy();
                        }
                        $('#policy_reports').empty();
                        if (dataTableInitialized) {
                            // table.clear().draw();
                        }

                        // Check if response is empty
                        if (response.data.length === 0) {
                            $('#policy_reports tbody').append(
                                '<tr><td colspan="11" class="text-center">No records found</td></tr>'
                                );
                        } else {
                            $('#policy_reports').html(`
                                <table class="table table-striped" id="policy_reports">
                                    <thead>
                                        <tr>
                                            <th scope="col">Product Key</th>
                                            <th scope="col">Business Type</th>
                                            <th scope="col">Premium Type</th>
                                            <th scope="col">Product Name</th>
                                            <th scope="col">Product Identifier</th>
                                            <th scope="col">Default Discount</th>
                                            <th scope="col">Pos Flag</th>
                                            <th scope="col" id="ownerTypeHeader">Owner Type</th>` + (isProductSubTypeIncluded ? `<th scope="col">GCV Carrier Type</th>` : ``) +
                                            `<th scope="col">Zero Dep</th>
                                            <th scope="col">Status <input type="checkbox" id="checkAllStatus"></th>
                                            <th scope="col">Consider For Visibility Report</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            `);
                           let contents;
                            // Append new table rows with response data
                            if(response.flow === 1)
                            {

                                contents += response.data

                                var selectId = response.selectId
                                console.log(selectId)
                                selectId.forEach((item,index)=>{
                                    console.log(item)
                                    $('#pos_flag_' + item).selectpicker();
                                    $('#owner' + item).selectpicker();
                                    $('.selectpicker').selectpicker();
                                })

                            }
                            else
                            {
                            $.each(response.data, function(index, report) {
                                var selectId = 'pos_flag_' + index;
                                var selectName = 'pos_flag[' + index + '][]';
                                var select = 'owner' + index;
                                var selectowner = 'owner[' + index + '][]';
                                let productName = report.product_unique_names ? report
                                    .product_unique_names : '';
                                // let businessType = report.bussiness_types ? report.bussiness_types.split(',') : [];
                                var checkboxChecked = report.status === 'Active';
                                var visibilityReportChecked = report.consider_for_visibility_report;
                                var statusCheckbox = '<input type="checkbox" name="status1[]" onchange="statuschange(this)" value="' + (checkboxChecked ? 'Active' : 'Inactive') + '"' + (checkboxChecked ? ' checked' : '') + '><br>' +
                                '<input type="hidden" name="status[]" value="Active"><br>';
                                if (!checkboxChecked) {
                                 statusCheckbox = '<input type="checkbox" name="status1[]" onchange="statuschange(this)" value="Inactive">' +
                                 '<input type="hidden" name="status[]" value="Inactive"><br>';
                                 }
                                if(visibilityReportChecked){
                                    var visibilityCheckBox = '<input type="checkbox" name="visibilityCheckBox[]" checked onchange="visibilityChange(this)"><input type="hidden" name="visibilityCheckBox1[]" value =1>';
                                }else{
                                    var visibilityCheckBox = '<input type="checkbox" name="visibilityCheckBox[]" onchange="visibilityChange(this)"><input type="hidden" name="visibilityCheckBox1[]" value =0>';
                                }
                                var gcvCarrierTypeDropdown = '';
                                   if (isProductSubTypeIncluded) {
                                       gcvCarrierTypeDropdown = '<td>' +
                                           '<select class="form-control gcv-carrier-type-dropdown" name="gcv_carrier_type[]">' +
                                           '<option value="Public"' + (report.gcv_carrier_types === 'Public' ? ' selected' : '') + '>Public</option>' +
                                           '<option value="Private"' + (report.gcv_carrier_types === 'Private' ? ' selected' : '') + '>Private</option>' +
                                           '</select>' +
                                           '</td>';
                                   }
                                   contents += '<tr>' +
                                    // '<td></td>' +
                                    '<td>' + '<span>' +report.product + '</span>' +
                                    '<input type="text" hidden name="product_key[]" value="' +
                                    report.product + '"></td>' +

                                    //     '<select name="' + selectBussiness + '" id="' + select + '" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100" required>' +
                                    // '<option value="newbusiness" ' + (report?.business_types?.includes('newbusiness') ? 'selected' : '') + '>newbusiness</option>' +
                                    // '<option value="rollover" ' + (report?.business_types?.includes('rollover') ? 'selected' : '') + '>rollover</option>' +
                                    // '<option value="breakin" ' + (report?.business_types?.includes('breakin') ? 'selected' : '') + '>breakin</option>' +
                                    // '</select>' +
                                    '<td>' +
                                    '<input type="hidden" name="business_types[]" value="' +
                                    (report.business_types ? report.business_types :
                                        '') + '">' +
                                    '<span>' + (report.business_types ? report
                                        .business_types : '') + '</span>' +
                                    '</td>' +
                                    '<td>' + report.premium_type +
                                    '<input type="text" hidden name="premium_type[]" value="' +
                                    report.premium_type + '"></td>' +
                                    // '<td>' + productName + '<input type="text" hidden name="product_unique_name[]" value="' + (report.product_unique_names ? report.product_unique_names : '') + '"></td>' +
                                    '<td><input type="hidden" name="product_name[]" value="' +
                                    report.product_names +
                                    '" pattern="[A-Za-z0-9\s]]+" title="Please enter only alphanumeric characters">' +
                                    report.product_names + '</td> ' +
                                    '<td><input type="hidden" name="product_identifier[]" value="' +
                                    (report.product_identifiers ? report
                                        .product_identifiers : '') +
                                    '" pattern="[A-Za-z0-9\s]+" title="Please enter only alphanumeric characters">' +
                                    (report.product_identifiers ? report
                                        .product_identifiers : '') + '</td>' +
                                    '<td><input class="form-control" type="text" name="default_discount[]" value="' +
                                    (report.default_discounts !== null ? report
                                        .default_discounts : '') +
                                    '" pattern="[0-9]*" title="Please enter only numeric characters" max="100"></td>' +
                                    '<td>' +
                                    '<select name="' + selectName + '" id="' +
                                    selectId +
                                    '" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100">' +
                                    '<option value="P" ' + (report?.pos_flags
                                        ?.includes('P') ? 'selected' : '') +
                                    '>POS</option>' +
                                    '<option value="N" ' + (report?.pos_flags
                                        ?.includes('N') ? 'selected' : '') +
                                    '>NONPOS</option>' +
                                    '<option value="D" ' + (report?.pos_flags
                                        ?.includes('D') ? 'selected' : '') +
                                    '>DRIVER APP</option>' +
                                    '<option value="A" ' + (report?.pos_flags
                                        ?.includes('A') ? 'selected' : '') +
                                    '>ESSONE</option>' +
                                    '<option value="EV" ' + (report?.pos_flags
                                        ?.includes('EV') ? 'selected' : '') +
                                    '>ELECTRIC VEHICLE</option>' +
                                    '<option value="E" ' + (report?.pos_flags
                                        ?.includes('E') ? 'selected' : '') +
                                    '>EMPLOYEE</option>' +
                                    '</select>' +
                                    '</td>' +
                                    '<td>' +
                                    '<select name="' + selectowner + '" id="' +
                                    select +
                                    '" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100" >' +
                                    '<option value="I" ' + (report.owner.includes(
                                        'I') ? 'selected' : '') +
                                    '>Individual</option>' +
                                    '<option value="C" ' + (report.owner.includes(
                                        'C') ? 'selected' : '') +
                                    '>Company</option>' +
                                    '</select>' +
                                    '</td>' +
                                    // '<td>' +
                                    // '<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 owner-type-dropdown" data-live-search="true"  name="owner_type[]">' +
                                    // '<option value="I"' + (report.owner === 'I' ? ' selected' : '') + '>Individual</option>' +
                                    // '<option value="C"' + (report.owner === 'C' ? ' selected' : '') + '>Company</option>' +
                                    // '</select>' +
                                    // '</td>' +


                                    // (GcvProduct.includes(productSubTypeName
                                    //         .toUpperCase()) ?
                                        // '<td>' +
                                        // '<select class="form-control gcv-carrier-type-dropdown" name="gcv_carrier_type[]">' +
                                        // '<option value="Public"' + (report
                                        //     .gcv_carrier_types === 'Public' ?
                                        //     ' selected' : '') + '>Public</option>' +
                                        // '<option value="Private"' + (report
                                        //     .gcv_carrier_types === 'Private' ?
                                        //     ' selected' : '') +
                                        // '>Private</option>' +
                                        // '</select>' + '</td>'
                                        gcvCarrierTypeDropdown
                                        // '<input type="hidden" name="gcv_carrier_type[]" value="">'
                                    // )
                                     +

                                    // '<td>' +
                                    // '<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 zero-dep-dropdown" data-live-search="true"  name="zero_dep[]">' +
                                    // '<option value="NA"' + (report.zero === 'NA' ? ' selected' : '') + '>NA</option>' +
                                    // '<option value="0"' + (report.zero === '0' ? ' selected' : '') + '>Zero Dept</option>' +
                                    // '<option value="1"' + (report.zero === '1' ? ' selected' : '') + '>Base Plan</option>' +
                                    // '<input type="hidden" name="zero_dep[]" value="' + (report.zero ? report.zero : '') + '">' +
                                    // '</select>' +
                                    // '</td>' +
                                    '<td>' +
                                    '<input type="hidden" name="zero_dep[]" value="' +
                                    (report.zero ? report.zero : '') + '">' +
                                    '<span>' + (report.zero === '0' ? 'Zero Dept' :
                                        (report.zero === '1' ? 'Base Plan' : (report
                                            .zero === 'NA' ? 'NA' : ''))) +
                                    '</span>' +
                                    '</td>' +

                                    '<td>' +
                                    statusCheckbox +
                                    // '</td>' +
                                    // '<td>'
                                        '<input type="hidden" name="policy_id[]" value="' +
                                    (report.policy_id ? report.policy_id : '') +
                                    '">' +
                                    '<input type="hidden" name="product_sub_type_id[]" value="' +
                                    (report.product_sub_type_ids ? report
                                        .product_sub_type_ids : '') + '">' +
                                    '<input type="hidden" name="insurance_company_id[]" value="' +
                                    (report.insurance_company_ids ? report
                                        .insurance_company_ids : '') + '">' +
                                    '<input type="hidden" name="premium_type_id[]" value="' +
                                    (report.premium_type_ids ? report
                                        .premium_type_ids : '') + '">' +
                                    '</td>' +
                                    '<td>' +
                                        visibilityCheckBox
                                    '</td>' +
                                    '</tr>'

                                // Initialize selectpickers
                                $('#' + selectId).selectpicker();
                                $('#' + select).selectpicker();
                                $('.selectpicker').selectpicker();

                            });
                            }
                            // $('#policy_reports tbody').destroy();
                            $('#policy_reports tbody').html(contents);
                            $('#policy_reports').DataTable({
                                "initComplete": function() {
                                var notApplyFilterOnColumn = [5, 6, 7, 8, 9, 10,11];
                                var showFilterBox = 'afterHeading';

                                $('.gtp-dt-filter-row').remove();
                                var theadSecondRow = '<tr class="gtp-dt-filter-row">';

                                $(this.api().columns().header()).each(function(index) {
                                    if (notApplyFilterOnColumn.indexOf(index) === -1) {
                                        theadSecondRow += '<td class="gtp-dt-select-filter-' + index + '"></td>';
                                    } else {
                                        theadSecondRow += '<td></td>';
                                    }
                                });

                                theadSecondRow += '</tr>';

                                if (showFilterBox === 'beforeHeading') {
                                    $(this.api().table().header()).prepend(theadSecondRow);
                                } else if (showFilterBox === 'afterHeading') {
                                    $(theadSecondRow).insertAfter($(this.api().table().header()));
                                }

                                this.api().columns().every(function(index) {
                                    var column = this;
                                    if (notApplyFilterOnColumn.indexOf(index) === -1) {
                                        var select = $('<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true"><option value="">Select</option></select>')
                                            .on('change', function() {
                                                var val = $.fn.dataTable.util.escapeRegex($(this).val());

                                                column
                                                    .search(val ? '^' + val + '$' : '', true, false)
                                                    .draw();
                                            });

                                        column.data().unique().sort().each(function(d, j) {
                                            let dataValue = '';
                                            let dataText = '';

                                            // Create a temporary DOM element to extract the text
                                            const tempElement = $('<div>').html(d);

                                            if (tempElement.find('input').length > 0) {
                                                dataText = tempElement.find('input').parent().text();
                                                dataValue = tempElement.find('input').val();
                                            } else {
                                                dataText = tempElement.text();
                                                dataValue = tempElement.text();
                                            }

                                            dataText = dataText.replace(/<[^>]*>?/gm, '');

                                            select.append('<option value="' + dataValue + '">' + dataText + '</option>');
                                        });

                                        $('td.gtp-dt-select-filter-' + index).html(select);
                                        $('.selectpicker').selectpicker();
                                    }
                                });
                                },
                                "paging": false,
                                "ordering": false,
                                "retrieve": true
                            });
                            // initializeDataTable();



                            // console.log('here');
                        }

                        if ( response.data != '') {
                            console.log(response.data);
                            $('#form_submit').removeAttr('hidden');
                        } else {
                            $('#form_submit').attr('hidden', true);
                            alert("No data found!")
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        // Handle the error response
                    }
                });
            });

            // // // Event listener for Save button click
            // $('#saveForm').submit(function(event) {
            //         // Prevent the default form submission behavior
            //         event.preventDefault()
            // })

            //     // Serialize the form data
            //     var formData = $(this).serialize();

            //     // Make an AJAX call to the update API
            //     $.ajax({
            //         url: '{{ url('api/ic-config/update') }}',
            //         method: 'POST',
            //         data: formData,
            //         success: function(response) {
            //             console.log(response);
            //             // Handle the success response
            //         },
            //         error: function(xhr, status, error) {
            //             console.error(xhr.responseText);
            //             // Handle the error response
            //         }
            //     });
            // });

            // $('#policy_reports').DataTable();
            $('#downloadExcelBtn').click(function() {
                var productSubTypeId = $('#broker_url').val();
                if(productSubTypeId === ""){
                    alert('Please select both item Product Name and Ic Name.');
                }
                window.location.href = '{{ route('admin.ic-config.download-excel') }}' +
                    '?product_sub_type_id=' + $('#broker_url').val() + '&insurance_company_id=' + $('#view')
                    .val();
            });

            // $('.datepickers').datepicker({
            //     todayBtn: "linked",
            //     autoclose: true,
            //     clearBtn: true,
            //     todayHighlight: true,
            //     toggleActive: true,
            //     format: "yyyy-mm-dd"
            // });
        });

        $(document).ready(function() {
            setTimeout(function() {
                $('#alertMessage').fadeOut('fast');
            }, 5000); // 10000 milliseconds = 10 seconds

        });
        $('#form_submit').click(function() {
            $('#product_sub_type_id').val($('#broker_url').val());
            $('#company_alias_id').val($('#view').val());
        });
        $('#nextBtn').click(function() {
            // Redirect to the next page
            window.location.href = '{{ url('admin/ic-config/credential') }}';
        });
        $('#newBtn').click(function() {
            window.location.href = '{{ url('admin/ic-config/miscellaneous') }}';
        });
        // $('#checkAllStatus').change(function() {
        //     console.log("hii");
        //     var isChecked = $(this).prop('checked');
        //     $('input[name="status1[]"]').prop('checked', isChecked);

        //     $('input[name="status1[]"]').val(isChecked ? 'Active' : 'Inactive');
        //     $('input[name="status[]"]').val(isChecked ? 'Active' : 'Inactive');
        // });
        $(document).ready(function() {
            $(document).on('change', '#checkAllStatus', function() {
                var isChecked = $(this).prop('checked');
                $('input[name="status1[]"]').prop('checked', isChecked).each(function() {
                    var newStatus = isChecked ? 'Active' : 'Inactive';
                    $(this).val(newStatus);
                    $(this).siblings('input[type="hidden"][name="status[]"]').val(newStatus);
                });
            });
        });

        function statuschange(checkbox) {
            var newValue = checkbox.checked ? 'Active' : 'Inactive';
            checkbox.value = newValue;
            $(checkbox).siblings('input[name="status[]"]').val(newValue);

            var uncheck = $('input[type=checkbox]');
            uncheck.each((i=0)=>{
                if( uncheck[i].name == 'status1[]'){
                    var  uncheck1 = $(uncheck[i]).parent().parent().children("td").children("div").children("select");
                    uncheck1.each((i=0)=>{
                        $( uncheck1[i]).removeAttr("required");
                    })
                }  
            })
            var check =$('input[type=checkbox]:checked');
            check.each((i=0)=>{
                if( check[i].name == 'status1[]'){
                    var check1 = $(check[i]).parent().parent().children("td").children("div").children("select");
                    check1.each((i=0)=>{
                        $(check1[i]).attr("required", "true");
                    })
                }  
            })
        }
    
        function visibilityChange(checkbox) {
            var newStatus = checkbox.checked ? 1 : 0;
            checkbox.value = newStatus;
            var hiddenInput = $(checkbox).siblings('input[type="hidden"]');
            hiddenInput.val(newStatus);
        }
         setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
@endpush
