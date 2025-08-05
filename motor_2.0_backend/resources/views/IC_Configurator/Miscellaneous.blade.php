@extends('layout.app', ['activePage' => 'IC_Configurator', 'titlePage' => __('IC Conf')])

@section('content')
<script src="{{ asset('js/ic_conf/jquery.min.js') }}"></script>
    <!-- partial -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Miscellaneous</h5>
                        <div class="form-group">
                            <form action="" method="get" id="policyReportForm" name="myForm"> <!-- Added an id to the form -->
                                <div class="row">
                                    @can('report.broker_name.show')
                                        <div class="col-md-12">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Insurance Company</label>
                                                        <select name="ic_url" id="ic_url" data-style="btn-sm btn-primary"
                                                            class="selectpicker w-100" data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            @foreach ($alias as $companyItem)
                                                                <option value="{{ $companyItem->company_alias }}">
                                                                    {{ $companyItem->company_name }}</option>
                                                                <!-- <option value="{{ $companyItem->insurance_company_id }}" {{ old('company_alias') == $companyItem->insurance_company_id ? 'selected' : '' }}>{{ $companyItem->company_name }}</option> -->
                                                            @endforeach
                                                        </select>
                                                        <div class="invalid-feedback ic_url_err"></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Section</label>
                                                        <select name="=section" id="section" data-style="btn-sm btn-primary"
                                                            class="selectpicker w-100" data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            <option value="Bike">Bike</option>
                                                            <option value="Car">Car</option>
                                                            <option value="CV">CV</option>
                                                        </select>
                                                        <div class="invalid-feedback section_err"></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Owner Type</label>
                                                        <select name="owner_type[]" id="owner_type"
                                                            data-style="btn-sm btn-primary" class="selectpicker w-100"
                                                            data-live-search="true">
                                                            <option value="">Nothing selected</option>
                                                            <option value="I">Individual</option>
                                                            <option value="C">Company</option>
                                                        </select>
                                                        <div class="invalid-feedback owner_type_err"></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2" id='search-button'>
                                                    <div class="d-flex align-items-center h-100">
                                                        <button type="submit" class="btn btn-outline-info btn-sm w-100">Search</button>
                                                    </div>
                                                </div>
                                                <div id="checkboxesContainer" style="padding:60px"></div>
                                            </div>
                                        </div>
                                    @endcan
                                </div>
                            </form>
                        </div>
                        <form action="{{ url('admin/ic-config/update-product') }}" method="POST" id="saveForm">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <!-- <input type="hidden" id="company_alias_id" name="company_alias" value="">
                            <input type="hidden" id="product_sub_type_id" name="product_sub_type" value=""> -->

                            <div class="table-responsive">
                                @if (session('status'))
                                    <div id="alertMessage" class="alert alert-{{ session('class') }}">
                                        {{ session('status') }}
                                    </div>
                                @endif
                                <table class="table table-striped" id="policy_reports">
                                    <thead>

                                    </thead>
                                    <tbody>

                                    </tbody>
                                </table>
                            </div>
                            <br>
                            <div class="col-md-2" id="submit_button_wrapper">
                                <div class="d-flex align-items-center h-100">
                                    <button type="submit" id="form_submit" hidden
                                        class="btn btn-outline-info btn-sm w-100">Save</button>
                                    <!-- Changed to type="submit" -->
                                </div>
                            </div>
                            <br>
                            <div class="d-flex align-items-left h-100">
                                <button type="button" id="nextBtn" class="btn btn-success mx-2"
                                    style="border-radius: 40px"><- Product</button>
                            </div>
                        </form>
                        <div id="successMessage" class="alert alert-success d-none">
                            <i class="fas fa-check-circle"></i> Saved successfully
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        function getCkycDropDown(response) {
            var fieldName = $('#checkbox_ckyc').val();
            var parentElement = $('#checkbox_ckyc').parent();
            var dropdown = parentElement.find('select');
            if ($('#checkbox_ckyc').is(':checked')) {
                if (fieldName === 'ckyc') {
                    if (dropdown.length === 0 || dropdown.length > 0) {
                        var dropdownHtml ='<select id="ckyc_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75 float-sm-left" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                        parentElement.append(dropdownHtml);
                        if (response?.data?.ckyc_type) {
                            var ckycTypeArray = response.data.ckyc_type;
                            var ckycTypeArrayOption = $('#ckyc_dropdown option').get();
                            var selectedElement = ckycTypeArray.map(function(elem) {
                                return elem.value;
                            });
                            ckycTypeArrayOption.forEach(function(option) {
                                if (selectedElement.includes(option.value)) {
                                    $(option).prop('selected', true);
                                }
                            });
                        } else {
                            console.error('Response does not contain a valid ckyc_type array:', response);
                        }
                    } else {
                        $('#checkbox_ckyc').parent().find('.dropdown').attr('hidden', false)

                    }
                }
            } else {
                $('#checkbox_ckyc').parent().find('.dropdown').attr('hidden', true);
            }

            $('.selectpicker').selectpicker();
        }
        //poi
        function getPoiDropDown(response) {
            var fieldName = $('#checkbox_poi').val();
            var parentElement = $('#checkbox_poi').parent();
            var dropdown = parentElement.find('select');
            if ($('#checkbox_poi').is(':checked')) {
                if (fieldName === 'poi') {
                    if (dropdown.length === 0 || dropdown.length > 0) {
                        var dropdownHtml =
                        '<select id="poi_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                        parentElement.append(dropdownHtml);
                        if (response?.data?.poilist) {
                            var ckycTypeArray = response.data.poilist;
                            var ckycTypeArrayOption = $('#poi_dropdown option').get();
                            var selectedElement = ckycTypeArray.map(function(elem) {
                                return elem.value;
                            });
                            ckycTypeArrayOption.forEach(function(option) {
                                if (selectedElement.includes(option.value)) {
                                    $(option).prop('selected', true);
                                }
                            });
                        } else {
                            console.error('Response does not contain a valid poi array:', response);
                        }
                    } else {
                        $('#checkbox_poi').parent().find('.dropdown').attr('hidden', false)

                    }
                }
            } else {
                $('#checkbox_poi').parent().find('.dropdown').attr('hidden', true);
            }

            $('.selectpicker').selectpicker();
        }
        //poa
        function getPoaDropDown(response) {
            var fieldName = $('#checkbox_poa').val();
            var parentElement = $('#checkbox_poa').parent();
            var dropdown = parentElement.find('select');
            if ($('#checkbox_poa').is(':checked')) {
                if (fieldName === 'poa') {
                    if (dropdown.length === 0 || dropdown.length > 0) {
                        var dropdownHtml = '<select id="poa_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                        parentElement.append(dropdownHtml);
                        if (response?.data?.poalist) {
                            var ckycTypeArray = response.data.poalist;
                            var ckycTypeArrayOption = $('#poa_dropdown option').get();
                            var selectedElement = ckycTypeArray.map(function(elem) {
                                return elem.value;
                            });
                            ckycTypeArrayOption.forEach(function(option) {
                                if (selectedElement.includes(option.value)) {
                                    $(option).prop('selected', true);
                                }
                            });
                        } else {
                            console.error('Response does not contain a valid poa array:', response);
                        }
                    } else {
                        $('#checkbox_poa').parent().find('.dropdown').attr('hidden', false)

                    }
                }
            } else {
                $('#checkbox_poa').parent().find('.dropdown').attr('hidden', true);
            }

            $('.selectpicker').selectpicker();
        }

        $(document).ready(function() {
            $('#policyReportForm').submit(function(event) {
                event.preventDefault();

                var insuranceCompanyId = $('#ic_url').val();
                var productSubType = $('#section').val();
                var premiumTypes = $('#owner_type').val();

                var isValid = false;
                var errorMsg = "";
                if (insuranceCompanyId === "") {
                    isValid = true;
                    errorMsg += 'Please select an item in Insurance Company ID.\n';
                }
                if(productSubType === ""){
                    var isValid = true;
                    errorMsg += 'Please select an item in Section.\n';
                }

                if(premiumTypes === ""){
                    var isValid = true;
                    errorMsg += 'Please select an item in Owner Type.\n';
                }

                if(isValid){
                    alert(errorMsg);
                    return false;
                }


                $.ajax({
                    url: '{{ url('api/getProposalFields') }}',
                    method: 'POST',
                    data: {
                        "company_alias": insuranceCompanyId,
                        "section": productSubType,
                        "owner_type": premiumTypes
                    },
                    success: function(response) {
                        $('#checkboxesContainer').empty();

                        var fieldDisplayNames =
                        {
                            "gstNumber": "Gst Number",
                            "maritalStatus": "Marital Status",
                            "occupation": "Occupation",
                            "panNumber": "Pan Number",
                            "dob": "Dob",
                            "gender" : "Gender",
                            "vehicleColor": "Vehicle color",
                            "hypothecationCity":"Hypothecation city",
                            "cpaOptOut":"Cpa opt out",
                            "cpaOptIn" :"Cpa opt in",
                            "email": "Email",
                            "pucNo": "Puc no",
                            "pucExpiry": "Puc expiry",
                            "representativeName" :"Representative Name",
                            "ncb" : "Ncb",
                            "inspectionType": "Inspection type",
                            "ckyc": "Ckyc",
                            "fileupload" : "File Upload",
                            "poi": "Poi",
                            "poa" : "Poa",
                            "photo" : "Photo",
                            "fatherName" : "Father name",
                            "relationType" : "Relation type",
                            "organizationType" : "Organization type",
                            "hazardousType" : "Hazardous type"
                        };

                        var mandatoryFields = [
                            "gstNumber",
                            "maritalStatus",
                            "occupation",
                            "panNumber",
                            "dob",
                            "gender",
                            "vehicleColor",
                            "hypothecationCity",
                            "cpaOptOut",
                            "cpaOptIn",
                            "email",
                            "pucNo",
                            "pucExpiry",
                            "representativeName",
                            "ncb",
                            "inspectionType",
                            "ckyc",
                            "fileupload",
                            "poi",
                            "poa",
                            "photo",
                            "fatherName",
                            "relationType",
                            "organizationType",
                            "hazardousType"
                        ];

                        var responseFields = Array.isArray(response.data) ? response.data :
                            response.data.fields;

                        var filteredFields = responseFields.filter(function(field) {
                            return field !== null && field !== 0;
                        });

                        var counter = 0;
                        var rowHtml = '<div class="row">';

                        if (mandatoryFields) {
                            mandatoryFields.forEach(function(field) {
                                var checked = '';

                                if (filteredFields.includes(field)) {
                                    checked = 'checked';
                                }

                                var displayName = fieldDisplayNames[field]; // Get the display name for the field

                                var checkboxHtml = '<div class="container col-md-2">' +
                                    '<div class="checkbox-container">' +
                                    '<div class="form-check d-flex flex-row gap-2">' +
                                    '<input class="form-check-input gap-5  checkbox-field" type="checkbox" value="' +
                                    field + '" id="checkbox_' + field + '" ' + checked +
                                    '>' +
                                    '<label class="form-check-label" style="margin-left: 0;" for="checkbox_' +
                                    field + '">' + displayName + '</label>' + // Use displayName instead of field
                                    '</div>' +
                                    '</div>' +
                                    '</div>';

                                rowHtml += checkboxHtml;
                                counter++;

                                if (counter % 5 === 0 || counter === mandatoryFields.length) {
                                    rowHtml += '</div>';
                                    $('#checkboxesContainer').append(rowHtml);
                                    if (counter !== mandatoryFields.length) {
                                        rowHtml = '<div class="row">';
                                    }
                                }
                            });
                            //ckyc
                            getCkycDropDown(response);
                            $('#checkbox_ckyc').on('change', function() {
                                var fieldName = $(this).val();
                                var parentElement = $(this).parent();
                                var dropdown = parentElement.find('select');
                                if ($(this).is(':checked')) {
                                    if (fieldName === 'ckyc') {
                                        if (dropdown.length === 0 || dropdown.length >
                                            0) {
                                            // Dropdown doesn't exist, create and append it
                                            var dropdownHtml =
                                            '<select id="ckyc_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                                            parentElement.append(dropdownHtml);
                                            if (response?.data?.ckyc_type) {
                                                var ckycTypeArray = response.data
                                                    .ckyc_type;
                                                var ckycTypeArrayOption = $(
                                                    '#ckyc_dropdown option').get();
                                                var selectedElement = ckycTypeArray?.map(
                                                    function(elem) {
                                                        return elem.value;
                                                    });
                                                ckycTypeArrayOption.forEach(function(
                                                    option) {
                                                    if (selectedElement
                                                        .includes(option.value)
                                                        ) {
                                                        $(option).prop(
                                                            'selected', true
                                                            );
                                                    }
                                                });
                                            } else {
                                                console.error(
                                                    'Response does not contain a valid ckyc_type array:',
                                                    response);
                                            }
                                        } else {
                                            $(this).parent().find('.dropdown').attr(
                                                'hidden', false)
                                        }
                                    }
                                } else {
                                    $(this).parent().find('.dropdown').attr('hidden',
                                        true);
                                }

                                $('.selectpicker').selectpicker();
                            });

                            //poi
                            getPoiDropDown(response);
                            $('#checkbox_poi').on('change', function() {
                                var fieldName = $(this).val();
                                var parentElement = $(this).parent();
                                var dropdown = parentElement.find('select');
                                if ($(this).is(':checked')) {
                                    if (fieldName === 'poi') {
                                        if (dropdown.length === 0 || dropdown.length >
                                            0) {
                                            var dropdownHtml =
                                            '<select id="poi_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                                            parentElement.append(dropdownHtml);
                                            if (response?.data?.poilist) {
                                                var ckycTypeArray = response.data
                                                    .poilist;
                                                var ckycTypeArrayOption = $(
                                                    '#poi_dropdown option').get();
                                                var selectedElement = ckycTypeArray?.map(
                                                    function(elem) {
                                                        return elem.value;
                                                    });
                                                ckycTypeArrayOption.forEach(function(
                                                    option) {
                                                    if (selectedElement
                                                        .includes(option.value)
                                                        ) {
                                                        $(option).prop(
                                                            'selected', true
                                                            );
                                                    }
                                                });
                                            } else {
                                                console.error(
                                                    'Response does not contain a valid ckyc_type array:',
                                                    response);
                                            }
                                        } else {
                                            $(this).parent().find('.dropdown').attr(
                                                'hidden', false)
                                        }
                                    }
                                } else {
                                    $(this).parent().find('.dropdown').attr('hidden',
                                        true);
                                }

                                $('.selectpicker').selectpicker();
                            });
                            //poa
                            getPoaDropDown(response);
                            $('#checkbox_poa').on('change', function() {
                                var fieldName = $(this).val();
                                var parentElement = $(this).parent();
                                var dropdown = parentElement.find('select');
                                if ($(this).is(':checked')) {
                                    if (fieldName === 'poa') {
                                        if (dropdown.length === 0 || dropdown.length >
                                            0) {
                                            var dropdownHtml =
                                            '<select id="poa_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-75" required>' +
                                            '<option value="panNumber" required>PAN Number</option>' +
                                            '<option value="drivingLicense" required>Driving License Number</option>' +
                                            '<option value="voterId" required>Voter ID Number</option>' +
                                            '<option value="gstNumber" required>GST Number</option>' +
                                            '<option value="passportNumber" required>Passport Number</option>' +
                                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                                            '<option value="aadharNumber" required>Adhaar Number</option>' +
                                            '<option value="cinNumber" required>CIN Number</option>' +
                                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                                            '<option value="udyog" required>Udyog Certificate</option>' +
                                            '<option value="udyam" required>Udyam Certificate</option>' +
                                            '</select>';
                                            parentElement.append(dropdownHtml);
                                            if (response?.data?.poalist) {
                                                var ckycTypeArray = response.data
                                                    .poalist;
                                                var ckycTypeArrayOption = $(
                                                    '#poa_dropdown option').get();
                                                var selectedElement = ckycTypeArray?.map(
                                                    function(elem) {
                                                        return elem.value;
                                                    });
                                                ckycTypeArrayOption.forEach(function(
                                                    option) {
                                                    if (selectedElement
                                                        .includes(option.value)
                                                        ) {
                                                        $(option).prop(
                                                            'selected', true
                                                            );
                                                    }
                                                });
                                            } else {
                                                console.error(
                                                    'Response does not contain a valid ckyc_type array:',
                                                    response);
                                            }
                                        } else {
                                            $(this).parent().find('.dropdown').attr(
                                                'hidden', false)
                                        }
                                    }
                                } else {
                                    $(this).parent().find('.dropdown').attr('hidden',
                                        true);
                                }

                                $('.selectpicker').selectpicker();
                            });
                            if ($('#checkboxesContainer').html() != '') {
                                $('#form_submit').removeAttr('hidden');
                            } else {
                                $('#form_submit').attr('hidden', true);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });

            $('#saveForm').submit(function(event) {
                event.preventDefault();

                var checkboxValues = [];
                var selectedDropdownValues = [];
                var ckycTypeValues = [];
                var poiTypeValues = [];
                var poaTypeValues = [];

                $('input[type=checkbox]').each(function() {
                    var fieldName = $(this).val();
                    var value = $(this).is(':checked') ? fieldName : 0;
                    checkboxValues.push(value);
                });

                $('.selectpicker').each(function() {
                    var selectedOptions = $(this).val(); // Get selected options
                    selectedDropdownValues = selectedDropdownValues.concat(selectedOptions);
                });

                // Retrieve CKYC values
                var ckycSelectedOptions = $('#checkbox_ckyc').siblings('.dropdown').find('option:selected');
                ckycSelectedOptions.each(function() {
                    var trimmedOption = $(this).val().trim();
                    if (trimmedOption !== '') { // Exclude empty options
                        ckycTypeValues.push({
                            "value": trimmedOption
                        });
                    }
                });
                var poiSelectedOptions = $('#checkbox_poi').siblings('.dropdown').find('option:selected');
                poiSelectedOptions.each(function() {
                    var trimmedOption = $(this).val().trim();
                    if (trimmedOption !== '') { // Exclude empty options
                        poiTypeValues.push({
                            "value": trimmedOption
                        });
                    }
                });
                var poaSelectedOptions = $('#checkbox_poa').siblings('.dropdown').find('option:selected');
                poaSelectedOptions.each(function() {
                    var trimmedOption = $(this).val().trim();
                    if (trimmedOption !== '') { // Exclude empty options
                        poaTypeValues.push({
                            "value": trimmedOption
                        });
                    }
                });

                var formData = {
                    fields: checkboxValues,
                    poilist: poiTypeValues,
                    poalist: poaTypeValues,
                    ckyc_type: ckycTypeValues,
                    company_alias: $('#ic_url').val(),
                    section: $('#section').val(),
                    owner_type: $('#owner_type').val()
                };
                $.ajax({
                    url: '{{ url('api/addProposalfield') }}',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    success: function(response) {
                        if (response.status) {
                            $('#successMessage').removeClass('d-none').fadeIn();
                            setTimeout(function() {
                                $('#successMessage').fadeOut('fast', function() {
                                    $(this).addClass('d-none');
                                });
                            }, 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });

            $('#nextBtn').click(function() {
                window.location.href = '{{ url('admin/ic-config/product_config') }}';
            });

            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });

            $(document).ready(function () {
                function refreshCheckboxesContainer() {
                    $('#checkboxesContainer').html('');
                }
                $('#ic_url').change(function () {
                    refreshCheckboxesContainer();
                });
            });
            $(document).ready(function() {
                $('#ic_url, #section, #owner_type').change(function() {
                    $('#checkboxesContainer').hide();
                    $('#form_submit').hide();
                });
            });
            $(document).ready(function() {
                $('#search-button').on('click', function() {
                    let x = document.forms["myForm"]["ic_url"].value;
                    let y = document.forms["myForm"]["owner_type[]"].value;
                    let z = document.forms["myForm"]["section"].value;
                    if( x != "" &&  y != "" &&  z != ""){
                        $('#checkboxesContainer').show();
                        $('#form_submit').show();
                    }
                });
            });

            setTimeout(function() {
                $('#alertMessage').fadeOut('fast');
            }, 5000);

            $('#form_submit').click(function() {
                $('#product_sub_type_id').val($('#ic_url').val());
                $('#company_alias_id').val($('#view').val());
            });
        });
    </script>
    {{-- <script>
        $('#ic_url').on('keypress', function (event) {
            if(event.key == 'Enter'){
            console.log('data');
        alert('d');
        }});
    </script> --}}
@endpush
