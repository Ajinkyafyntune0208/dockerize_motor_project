@extends('admin_lte.layout.app', ['activePage' => 'Miscellaneous', 'titlePage' => __('Miscellaneous')])
@section('content')
    <!-- partial -->
    <div class="content">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        {{-- <h3 class="card-title">Miscellaneous</h3><br><br> --}}
                        <div class="form-group">
                            <form action="" method="get" id="policyReportForm"> <!-- Added an id to the form -->
                                <div class="row">
                                        <div class="col-md-12">
                                        <div id="successMessage">

                                        </div>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Insurance Company</label>
                                                        <select name="ic_url" id="ic_url" data-style="btn btn-light"
                                                            class="border selectpicker w-100" data-live-search="true" required oninvalid="this.setCustomValidity('Please select an IC in the list.')" oninput="this.setCustomValidity('')">
                                                            <option value="">Nothing selected</option>
                                                            @foreach ($alias as $companyItem)
                                                                <option value="{{ $companyItem->company_alias }}">
                                                                    {{ $companyItem->company_name }}</option>
                                                                <!-- <option value="{{ $companyItem->insurance_company_id }}" {{ old('company_alias') == $companyItem->insurance_company_id ? 'selected' : '' }}>{{ $companyItem->company_name }}</option> -->
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Section</label>
                                                        <select name="=section" id="section" data-style="btn btn-light"
                                                            class="border selectpicker w-100" data-live-search="true" required oninvalid="this.setCustomValidity('Please select a section in the list.')" oninput="this.setCustomValidity('')">
                                                            <option value="">Nothing selected</option>
                                                            <option value="Bike">Bike</option>
                                                            <option value="Car">Car</option>
                                                            <option value="CV">CV</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group w-100">
                                                        <label class = "required">Owner Type</label>
                                                        <select name="owner_type[]" id="owner_type"
                                                            data-style="btn btn-light" class="border selectpicker w-100"
                                                            data-live-search="true" required oninvalid="this.setCustomValidity('Please select a owner type in the list.')" oninput="this.setCustomValidity('')">
                                                            <option value="">Nothing selected</option>
                                                            <option value="I">Individual</option>
                                                            <option value="C">Company</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-12" id='search-button'>
                                                        <input type="submit" class="btn btn-info btn-md" style="float:right" value="Search">
                                                </div>
                                                <div class="col-md-12" id="successMessage"> </div>
                                                <div class="col-md-12 m-5" id="checkboxesContainer"></div>
                                            </div>
                                        </div>
                                </div>
                            </form>
                        </div>

                        <form action="{{ url('admin/ic-config/update-product') }}" method="POST" id="saveForm">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">

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
                            <div class="col-md-12" id="submit_button_wrapper">
                                <!-- <div class="d-flex align-items-center h-100"> -->
                                    <button type="submit" id="form_submit" hidden class="btn btn-outline-dark btn-sm" style="width:150px; float:right">Save</button>
                                    <!-- Changed to type="submit" -->
                                <!-- </div> -->
                            </div>
                            <br>
                            <div class="d-flex align-items-left h-100">
                                <button type="button" id="nextBtn" class="btn btn-success mx-2"
                                    style="border-radius: 40px"><i class="fa fa-arrow-left mr-2"></i>Product</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>



    <script>
        function getCkycDropDown(response) {
            var fieldName = $('#checkbox_ckyc').val();
            var parentElement = $('#checkbox_ckyc').parent();
            var dropdown = parentElement.find('select');
            if ($('#checkbox_ckyc').is(':checked')) {
                if (fieldName === 'ckyc') {
                    if (dropdown.length === 0 || dropdown.length > 0) {
                        var dropdownHtml ='<select id="ckyc_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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
                        '<select id="poi_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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
                        var dropdownHtml = '<select id="poa_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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

        function getProprietorshipDropDown(response) {
            var fieldName = $('#checkbox_proprietorship').val();
            var parentElement = $('#checkbox_proprietorship').parent();
            var dropdown = parentElement.find('select');

            if ($('#checkbox_proprietorship').is(':checked')) {
                if (fieldName === 'proprietorship') {
                    if (dropdown.length === 0) {
                        var dropdownHtml =
                            '<select id="proprietorship_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
                            '<option value="panNumber" required>PAN Number</option>' +
                            '<option value="drivingLicense" required>Driving License Number</option>' +
                            '<option value="voterId" required>Voter ID Number</option>' +
                            '<option value="gstNumber" required>GST Number</option>' +
                            '<option value="passportNumber" required>Passport Number</option>' +
                            '<option value="passportFileNumber" required>Passport File Number</option>' +
                            '<option value="e-eiaNumber" required>e-Insurance Account Number</option>' +
                            '<option value="aadharNumber" required>Aadhaar Number</option>' +
                            '<option value="cinNumber" required>CIN Number</option>' +
                            '<option value="nregaJobCard" required>NREGA Job Card</option>' +
                            '<option value="nationalPopulationRegisterLetter" required>National Population Letter</option>' +
                            '<option value="registrationCertificate" required>Registration Certificate</option>' +
                            '<option value="cretificateOfIncorporaion" required>Certificate of Incorporation</option>' +
                            '<option value="udyog" required>Udyog Certificate</option>' +
                            '<option value="udyam" required>Udyam Certificate</option>' +
                            '</select>';
                        parentElement.append(dropdownHtml);
                    }

                    if (response?.data?.proprietorship_type) {
                        var proprietorshipTypeArray = response.data.proprietorship_type;
                        var dropdownOptions = $('#proprietorship_dropdown option').get();
                        var selectedValues = proprietorshipTypeArray.map(function(elem) {
                            return elem.value;
                        });

                        dropdownOptions.forEach(function(option) {
                            if (selectedValues.includes(option.value)) {
                                $(option).prop('selected', true);
                            }
                        });
                    } else {
                        console.error('Response does not contain a valid proprietorship array:', response);
                    }
                    $('#checkbox_proprietorship').parent().find('.dropdown').attr('hidden', false);
                }
            } else {
                $('#checkbox_proprietorship').parent().find('.dropdown').attr('hidden', true);
            }
            $('.selectpicker').selectpicker();
        }

        function getClaimcountDropDown(response) {
            var productSubType = $('#section').val();
            var premiumTypes = $('#owner_type').val();

            var fieldName = $('#checkbox_claimcount').val();
            var parentElement = $('#checkbox_claimcount').parent();
            var dropdown = parentElement.find('select');
            if ($('#checkbox_claimcount').is(':checked')) {
                if (fieldName === 'claimcount') {
                    
                    var one = '';
                    var two = '';
                    var unlimited = '';
                    if (response?.data?.godigit_claim_covered) {
                        if(response.data.godigit_claim_covered=='ONE')
                            one = ' selected';
                        if(response.data.godigit_claim_covered=='TWO')
                            two = ' selected';
                        if(response.data.godigit_claim_covered=='UNLIMITED')
                            unlimited = ' selected';
                    }else{
                        one = ' selected';
                    }

                    if (dropdown.length === 0) {
                        var dropdownHtml =
                        '<select id="claim_count_option" data-style="btn-cus-v2" class="selectpicker w-100 dropdown" required>' +
                        '<option value="ONE" '+one+' required>One</option>' +
                        '<option value="TWO" '+two+' required>Two</option>' +
                        '<option value="UNLIMITED" '+unlimited+' required>Unlimited</option>' +
                        '</select>';
                        parentElement.append(dropdownHtml);
                                            
                    }

                    if (response?.data?.godigit_claim_covered) {
                        var claim_count = response.data.godigit_claim_covered;
                    } else {
                        console.error(
                            'Response does not contain a valid claim count array:',
                            response);
                    }
                    $('#checkbox_claimcount').parent().find('.dropdown').attr('hidden', false);
                }
            } else {
                $('#checkbox_claimcount').parent().find('.dropdown').attr('hidden', true);
            }
            $('.selectpicker').selectpicker();
        }

        $(document).ready(function() {
            $('#policyReportForm').submit(function(event) {
                event.preventDefault();

                var insuranceCompanyId = $('#ic_url').val();
                var productSubType = $('#section').val();
                var premiumTypes = $('#owner_type').val();

            if (!insuranceCompanyId || !productSubType || !premiumTypes) {
                event.preventDefault();  
                alert("Please fill all required fields");
                return false;
            }
                $.ajax({
                    url: getProposalFields,
                    method: 'GET',
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
                            "proprietorship": "Proprietorship"  ,
                            "maritalStatus": "Marital Status",
                            "occupation": "Occupation",
                            "panNumber": "Pan Number",
                            "dob": "Dob",
                            "gender" : "Gender",
                            "vehicleColor": "Vehicle color",
                            "hypothecationCity":"Hypothecation city",
                            "cpaOptOut":"Cpa opt out",
                            "cpaOptin" :"Cpa opt in",
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
                            "hazardousType" : "Hazardous type",
                            "cisEnabled": "Is CIS Enabled",
                            "claimcount": "Claim Count",
                            "ckycQuestion": "Remove Ckyc Question",
                            "directOvdFileUploadFlow": "direct ovd file upload flow"
                            // "cisIfsc": "IFSC",
                            // "cisBankName":"Bank Name",
                            // "cisAccountNumber":"Account Number",
                            // "cisPoliticallyExposedPerson":"Politically Exposed Person",
                            // "cisPolicyHardCopy":"Do you need policy hard copy",
                            // "cisRestrictionOfPan":"Restriction of PAN mandatory",
                            // "cisRestrictionOfNominee":"Restriction of Nominee details mandatory"
                        };

                        var mandatoryFields = [
                            "gstNumber",
                            "proprietorship" ,
                            "maritalStatus",
                            "occupation",
                            "panNumber",
                            "dob",
                            "gender",
                            "vehicleColor",
                            "hypothecationCity",
                            "cpaOptOut",
                            "cpaOptin",
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
                            "hazardousType",
                            "cisEnabled",
                            "ckycQuestion",
                            "directOvdFileUploadFlow"
                            // "cisIfsc",
                            // "cisBankName",
                            // "cisAccountNumber",
                            // "cisPoliticallyExposedPerson",
                            // "cisPolicyHardCopy",
                            // "cisRestrictionOfPan",
                            // "cisRestrictionOfNominee"
                        ];

                        if(insuranceCompanyId == 'godigit' && productSubType=='Car')
                            mandatoryFields.push('claimcount');    

                        var responseFields = Array.isArray(response.data) ? response.data :
                        response.data.fields;
                        

                        var filteredFields = responseFields.filter(function(field) {
                            return field !== null && field !== 0;
                        });

                        var showProprietorship = (premiumTypes === 'C');

                        var counter = 0;
                        var rowHtml = '<div class="row">';

                        if (mandatoryFields) {
                            mandatoryFields.forEach(function(field) {
                                var checked = '';

                                if (filteredFields.includes(field)) {
                                    checked = 'checked';
                                }            
                                if (field === "proprietorship" && !showProprietorship) {
                                    return; 
                                }

                                var displayName = fieldDisplayNames[field] || field; // Get the display name for the field
                                // if (field === 'cisIfsc') {
                                //     rowHtml += '<div class="col-12"><h5 class="mt-5 mb-0">Bank Details</h5></div>';
                                // }

                                var checkboxHtml = '<div class="col-md-3 m-2">' +
                                    '<div class="form-check">' +
                                    '<input class="form-check-input checkbox-field" type="checkbox" value="' +
                                    field + '" id="checkbox_' + field + '" ' + checked +
                                    '>' +
                                    '<label class="form-check-label" style="margin-left: 0;" for="checkbox_' +
                                    field + '">' + displayName + '</label>' + // Use displayName instead of field
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
                            if (counter % 5 !== 0 && !showProprietorship)  {
                                        rowHtml += '</div>';
                                        $('#checkboxesContainer').append(rowHtml);

                                    }
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
                                            '<select id="ckyc_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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
                                            '<select id="poi_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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
                            // //poa
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
                                            '<select id="poa_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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
                            //Proprietorship
                            getProprietorshipDropDown(response);
                            $('#checkbox_proprietorship').on('change', function() {
                                var fieldName = $(this).val();
                                var parentElement = $(this).parent();
                                var dropdown = parentElement.find('select');
                                if ($(this).is(':checked')) {
                                    if (fieldName === 'proprietorship') {
                                        if (dropdown.length === 0 || dropdown.length > 0) {
                                            var dropdownHtml =
                                                '<select id="poi_dropdown" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true" multiple class="selectpicker w-100 dropdown" required>' +
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

                                            if (response?.data?.proprietorshiplist) {
                                                var proprietorshipTypeArray = response.data.proprietorshiplist;
                                                var proprietorshipTypeArrayOption = $('#proprietorship_dropdown option').get();
                                                var selectedElement = proprietorshipTypeArray?.map(function(elem) {
                                                    return elem.value;
                                                });
                                                
                                                proprietorshipTypeArrayOption.forEach(function(option) {
                                                    if (selectedElement.includes(option.value)) {
                                                        $(option).prop('selected', true);
                                                    }
                                                });
                                            } else {
                                                console.error('Response does not contain a valid proprietorship array:', response);
                                            }
                                        } else {
                                            $(this).parent().find('.dropdown').attr('hidden', false);
                                        }
                                    }
                                } else {
                                    $(this).parent().find('.dropdown').attr('hidden', true);
                                }

                                $('.selectpicker').selectpicker();
                            });


                            getClaimcountDropDown(response);
                            $('#checkbox_claimcount').on('change', function() {
                                var fieldName = $(this).val();
                                var parentElement = $(this).parent();
                                var dropdown = parentElement.find('select');
                                if ($(this).is(':checked')) {
                                            var dropdownHtml =
                                            '<select id="claim_count_option" data-style="btn-cus-v2" class="selectpicker w-100 dropdown" required>' +
                                            '<option value="ONE" required>One</option>' +
                                            '<option value="TWO" required>Two</option>' +
                                            '<option value="UNLIMITED" required>Unlimited</option>' +
                                            '</select>';
                                            parentElement.append(dropdownHtml);
                                            if (response?.data?.godigit_claim_covered) {
                                                var claim_count = response.data
                                                    .godigit_claim_covered;
                                            } else {
                                                console.error(
                                                    'Response does not contain a valid claim count array:',
                                                    response);
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
                var proprietorshipTypeValues = [];
                var claimCountValues = 'ONE';

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

                //Proprietorship 
                var proprietorshipSelectedOptions = $('#checkbox_proprietorship').siblings('.dropdown').find('option:selected');
                proprietorshipSelectedOptions.each(function() {
                    var trimmedOption = $(this).val().trim();
                    if (trimmedOption !== '') { // Exclude empty options
                        proprietorshipTypeValues.push({
                            "value": trimmedOption
                        });
                    }
                });

                //Claim Count 
                var claimCountSelectedOptions = $('#checkbox_claimcount').siblings('.dropdown').find('option:selected');
                claimCountSelectedOptions.each(function() {
                    var trimmedOption = $(this).val().trim();
                    if (trimmedOption !== '') { // Exclude empty options
                        claimCountValues = trimmedOption;
                    }
                });


                var formData = {
                    fields: checkboxValues,
                    poilist: poiTypeValues,
                    poalist: poaTypeValues,
                    ckyc_type: ckycTypeValues,
                    proprietorship_list: proprietorshipTypeValues, 
                    claimcount_list: claimCountValues, 
                    company_alias: $('#ic_url').val(),
                    section: $('#section').val(),
                    owner_type: $('#owner_type').val()
                };
                $.ajax({
                    url: addProposalfield,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    },
                    success: function(response) {
                        if(response.status) {
                            $('#successMessage').html('<div class="alert alert-success"> \
                                            <i class="fas fa-check-circle"></i> Data saved successfully \
                                        </div>');
                            var scrollPos = $("#policyReportForm").offset().top;
                            $(window).scrollTop(scrollPos);
                            setTimeout(function() {
                                $('#successMessage').html('');
                            }, 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });

            $('#nextBtn').click(function() {
                window.location.href = '{{url('admin/ic-config/product_config')}}';
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

            $('#ic_url, #section, #owner_type').on('change', function() {
              if ($(this).val() !== '') {
                this.setCustomValidity(''); 
                }
        });
        
            });
            $(document).ready(function() {
                $('#search-button').on('click', function() {
                    $('#checkboxesContainer').show();
                    $('#form_submit').show();
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

<script>
    const getProposalFields = "{{ url('admin/ic-config/getProposalFields') }}";
    const addProposalfield = "{{url('admin/ic-config/addProposalfield')}}";
 </script>

@endsection
