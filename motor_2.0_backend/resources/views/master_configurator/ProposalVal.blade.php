@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Proposal Validation')])
@section('content')
<style>
    :root{
    --primary: #1f3bb3;
    }
    .btn-cus{
        width: 100%;
        height: 40px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);

        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
</style>
<div class="content-wrapper">

    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Master Configurator</h5>
                    <div class="row mb-3" style="gap:10px;">
                        {{-- <div class="col-12 col-sm-2">
                            <a href="" class="btn btn-cus">Theme Config</a>
                        </div> --}}
                        <div class="col-12 col-sm-2">
                            @can('configurator.field')
                                <a href="{{ route('admin.config-field') }}" class="btn btn-cus">Field Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.onboarding')
                                <a href="{{ route('admin.config-onboarding') }}" class="btn btn-cus">OnBoarding Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.proposal')
                                <a href="{{route('admin.config-proposal-validation')}}" class="btn btn-cus active">Proposal Validation</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.OTP')
                                <a href="{{ route('admin.config-otp') }}" class="btn btn-cus">OTP Config</a>
                            @endcan
                        </div>

                    </div>
                    <div id="successMessage" class="alert alert-success" style="display: none;">Proposal Validation submitted successfully!</div>
                    <div id="failMessage" class="alert alert-danger" style="display: none;">Submission Failed!</div>

                    <h5 class="card-title">Proposal Validation
                        <button id ="reset" class="view btn btn-primary float-end btn-sm">Reset</button>
                    </h5>
                    <form id="addPorp" method="POST">
                        @csrf

                        @php
                            function fil($string)
                            {
                                $words = explode('_', $string);
                                $titleCaseWords = array_map('ucfirst', $words);
                                return implode(' ', $titleCaseWords);
                            }
                        @endphp
                        <div class="row">
                            <div class="col-12 col-sm-6 form-group">
                                <label class ="required" for="selectIC">Select IC</label>
                                <select  data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" id="selectIC" name="selectIC" required>
                                    <option value="" selected>Select a IC </option>
                                    <option value="all" >All</option>
                                    @foreach ($comp as $dat )
                                        <option value="{{$dat}}">{{fil($dat)}}</option>
                                    @endforeach
                                </select>
                                <sub  class=" selectIcerror text-danger" style="display: none;">Please Select a Ic!</sub>
                            </div>
                            <div class="col-12 col-sm-6 form-group">
                                <label class="required"for="journeytype">Select New/Rollover</label>
                                <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" id="journeytype" required>
                                    <option value="" selected>Select type </option>
                                    <option value="NEW">New</option>
                                    <option value="Rollover">Rollover</option>
                                </select>
                                <sub  class=" selectJtypeerror text-danger" style="display: none;">Please Select Journey Type!</sub>

                            </div>

                        </div>
                        <h4>Engine Number</h4>
                        <div class="row">
                            <div class="col-12 col-sm-3 form-group">
                                <label class="required" for="minengine">Min</label>
                                <input class="form-control" type="number"  maxlength="10" name="minengine" id="minengine" required>
                                <sub  class=" minerror text-danger" style="display: none;">Minimum number is required!</sub>
                                <sub  class=" inputerror text-danger" style="display: none;">Minimum should be less than maxiumum!</sub>
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label class="required" for="maxengine">Max</label>
                                <input class="form-control" type="number" min="1" name="maxengine" id="maxengine" required>
                                <sub  class=" maxerror text-danger" style="display: none;">Maximum number is required!</sub>
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label for="regxengine">Regular Expression <span style="font-size: 9px;">RegEx</span></label>
                                <input class="form-control" type="text" name="regxengine" id="regxengine" >
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label for="engineRegxFailureMsg">RegEx failure Error Msg</label>
                                <input class="form-control" type="text" name="engineRegxFailureMsg" id="engineRegxFailureMsg" >
                                <sub  class="engineRegxFailureMsg text-danger" style="display: none;">RegEx error message is required.</sub>
                            </div>
                        </div>
                        <h4>Chassis Number</h4>
                        <div class="row">
                            <div class="col-12 col-sm-3 form-group">
                                <label class="required" for="minchassis">Min</label>
                                <input class="form-control" type="number"  maxlength="10" name="minchassis" id="minchassis" required>
                                <sub  class=" minerror text-danger" style="display: none;">Minimum number is required!</sub>
                                <sub  class=" inputerror text-danger" style="display: none;">Minimum should be less than maxiumum!</sub>
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label class="required" for="maxchassis">Max</label>
                                <input class="form-control" type="number" min="1" name="maxchassis" id="maxchassis" required>
                                <sub  class=" maxerror text-danger" style="display: none;">Maximum number is required!</sub>
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label for="regxchassis">Regular Expression <span style="font-size: 9px;">RegEx</span></label>
                                <input class="form-control" type="text" name="regxchassis" id="regxchassis" >
                            </div>
                            <div class="col-12 col-sm-3 form-group">
                                <label for="chassisRegxFailureMsg">RegEx failure Error Msg</label>
                                <input class="form-control" type="text" name="chassisRegxFailureMsg" id="chassisRegxFailureMsg" >
                                <sub  class="chassisRegxFailureMsg text-danger" style="display: none;">RegEx error message is required.</sub>
                            </div>
                        </div>

                        <button type="button" id="submit" class="btn btn-primary">Submit</button>
                    </form>
                    <div class="mt-2">
                        <h4 class="alert alert-danger">Note : Regex for allowing only (Alpha-Numeric or Numeric) characters : <strong>^(?=.*\d)[a-zA-Z0-9]+$</strong></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Configured Proposal Validation Preview </h4>
                    <p class="text-primary">Note: To view new changes in table below,please refresh the page.</p>
                    @if (isset($data) && !empty($data))
                    @php
                        $data = json_decode($data['data'],true);
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th rowspan="2" colspan="2">IC Name</th>
                                    <th rowspan="2" colspan="2">Journey Type</th>
                                    <th colspan="2">Engine Number</th>
                                    <th class="text-center" colspan="2">Chassis Number</th>
                                    <th class="text-center" colspan="2">Regular Expression</th>
                                    <th class="text-center" colspan="2">Regular Expression Error Message</th>
                                </tr>
                                <tr>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th class="text-center">Engine</th>
                                    <th class="text-center">Chassis</th>
                                    <th class="text-center">Engine</th>
                                    <th class="text-center">Chassis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $JourneyType =[
                                        'NEW' ,
                                        'Rollover'
                                    ]
                                @endphp
                                @foreach ($data as $key=>$value)
                                        <tr>
                                            <td colspan="2" rowspan="2">{{ isset($data[$key]['IcName']) ? fil($data[$key]['IcName']) : 'Not mentioned'}}</td>
                                        @foreach ($JourneyType as $item)
                                            @if ( $item == 'NEW' )
                                                <td colspan="2" >{{$item}}</td>
                                                <td >{{ isset($data[$key][$item]['engineNomin'])? $data[$key][$item]['engineNomin'] : 'NA'}}</td>
                                                <td>{{ isset($data[$key][$item]['engineNomax'])? $data[$key][$item]['engineNomax'] : 'NA'}}</td>
                                                <td>{{ isset($data[$key][$item]['chasisNomin'])? $data[$key][$item]['chasisNomin'] :'NA' }}</td>
                                                <td>{{isset($data[$key][$item]['chasisNomax']) ? $data[$key][$item]['chasisNomax'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['regxengine']) ? $data[$key][$item]['regxengine'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['regxchassis']) ? $data[$key][$item]['regxchassis'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['engineRegxFailureMsg']) ? $data[$key][$item]['engineRegxFailureMsg'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['chassisRegxFailureMsg']) ? $data[$key][$item]['chassisRegxFailureMsg'] : 'NA'}}</td>
                                            </tr>
                                            @elseif ($item == 'Rollover' )
                                            <tr>
                                                <td colspan="2" >{{$item}}</td>
                                                <td >{{ isset($data[$key][$item]['engineNomin'])? $data[$key][$item]['engineNomin'] : 'NA'}}</td>
                                                <td>{{ isset($data[$key][$item]['engineNomax'])? $data[$key][$item]['engineNomax'] : 'NA'}}</td>
                                                <td>{{ isset($data[$key][$item]['chasisNomin'])? $data[$key][$item]['chasisNomin'] :'NA' }}</td>
                                                <td>{{isset($data[$key][$item]['chasisNomax']) ? $data[$key][$item]['chasisNomax'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['regxengine']) ? $data[$key][$item]['regxengine'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['regxchassis']) ? $data[$key][$item]['regxchassis'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['engineRegxFailureMsg']) ? $data[$key][$item]['engineRegxFailureMsg'] : 'NA'}}</td>
                                                <td class="text-center">{{isset($data[$key][$item]['chassisRegxFailureMsg']) ? $data[$key][$item]['chassisRegxFailureMsg'] : 'NA'}}</td>
                                            </tr>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                        <div class="alert alert-warning" role="alert">No records found!</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/jquery-3.7.0.min.js') }}" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>

<script>

    //Backup code to avoid any mishape (works for single selected ic update and add)
    // $(document).ready(function () {
    //     var propic = "";
    //     var ic = "";
    //     var etic = "";
    //     var jtype = "";
    //     function sortic(data,ic) {

    //         for( i=0 ; i < data.data.length ; i++){
    //             if (data.data[i].IcName == ic ){
    //                 // console.log(data.data[i].IcName);
    //                 return data.data[i];
    //             }
    //         }
    //     }

    // function validateForm() {
    //     let isValid = true;
    //     if ( $("#selectIC").val() === "" ){
    //         $(".selectIcerror").fadeIn().delay(3000).fadeOut();
    //         // alert("Select Ic");
    //         isValid = false;
    //     }
    //     if ( $("#journeytype").val() === ""  ){

    //         $(".selectJtypeerror").fadeIn().delay(3000).fadeOut();
    //         // alert("Select Journey Type");
    //         isValid = false;
    //     }
    //     if (
    //         $("#maxengine").val() === "" ||
    //         $("#minengine").val() === "" ||
    //         $("#maxchassis").val() === "" ||
    //         $("#minchassis").val() === ""    ) {

    //         $(".minerror").fadeIn().delay(3000).fadeOut();
    //         $(".maxerror").fadeIn().delay(3000).fadeOut();
    //         // alert("Please fill all the fields");
    //         isValid = false;
    //     }

    //     if (
    //         parseFloat($("#maxengine").val()) < parseFloat($("#minengine").val()) ||
    //         parseFloat($("#maxchassis").val()) < parseFloat($("#minchassis").val())  ) {

    //         // alert("Minimum number inputted is greater than maximum number!");
    //         $(".inputerror").fadeIn().delay(3000).fadeOut();
    //         isValid = false;
    //     }

    //     return isValid;
    // }
    //     function populateFormFields() {

    //         if (propic && propic.hasOwnProperty(jtype) ) {
    //             $("#minengine").val(propic[jtype].engineNomin);
    //             $("#maxengine").val(propic[jtype].engineNomax);
    //             $("#minchassis").val(propic[jtype].chasisNomin);
    //             $("#maxchassis").val(propic[jtype].chasisNomax);
    //             $("#regxengine").val(propic[jtype].regxengine);
    //             $("#regxchassis").val(propic[jtype].regxchassis);

    //         } else {
    //             // Clear the form fields if the journey type is not found in propic
    //             $("#minengine").val("");
    //             $("#maxengine").val("");
    //             $("#minchassis").val("");
    //             $("#maxchassis").val("");
    //             $("#regxengine").val("");
    //             $("#regxchassis").val("");
    //         }
    //     }

    //     $("#reset").click(function () {

    //         $("#selectIC").val("");
    //         $("#journeytype").val("");
    //         $("#maxengine").val("");
    //         $("#minengine").val("");
    //         $("#maxchassis").val("");
    //         $("#minchassis").val("")

    //     });

    //     //Dont modify this commented code
    //     $("#selectIC").change(function () {
    //         ic = $(this).val();
    //         // alert(ic);
    //         $.getJSON("/api/getIcList", {}, function (data) {
    //             $.getJSON("/api/getProposalValidation", {}, function (data) {
    //                 etic = data;
    //                 // console.log(data.data);
    //                 propic = sortic(data, ic);
    //                 // console.log(propic);
    //                 // Call the function to populate form fields dynamically
    //                 populateFormFields();

    //             });
    //         });

    //         $("#journeytype").val("");
    //         $("#maxengine").val("");
    //         $("#minengine").val("");
    //         $("#maxchassis").val("");
    //         $("#minchassis").val("") // Call the function to populate form fields dynamically
    //     });

    //     // Event listener for the change event of the journeytype element
    //     $("#journeytype").change(function () {
    //         jtype = $(this).val();
    //         if (ic && jtype) {
    //             populateFormFields(); // Call the function to populate form fields dynamically
    //         }
    //     });


    //     $("#submit").click(function () {
    //         if (validateForm()) {

    //             let data1val = [];
    //             data1val = etic.data;
    //             // console.log(data1val);

    //             let data2val = {};
    //             data2val['IcName'] = $("#selectIC").val();
    //                 data2val[$('#journeytype').val()] = {
    //                 engineNomax: $("#maxengine").val(),
    //                 engineNomin: $("#minengine").val(),
    //                 chasisNomax: $("#maxchassis").val(),
    //                 chasisNomin: $("#minchassis").val(),
    //                 regxengine: $("#regxengine").val(),
    //                 regxchassis: $("#regxchassis").val()

    //             };

    //             // console.log(data2val);

    //             let existingIndex = -1;
    //             data1val.forEach((entry, index) => {
    //                 if (entry['IcName'] === data2val['IcName']) {
    //                     existingIndex = index;
    //                     return false;
    //                 }
    //             });

    //             if (existingIndex !== -1) {
    //                 // Merge the data into the existing entry
    //                 Object.assign(data1val[existingIndex], data2val);
    //             } else {
    //                 // Add data2val as a new entry in the array
    //                 data1val.push(data2val);
    //             }

    //             // Filter and keep unique entries based on 'IcName'
    //             let uniqueData = data1val.reduce((acc, current) => {
    //                 const x = acc.find(item => item.IcName === current.IcName);
    //                 if (!x) {
    //                     return acc.concat([current]);
    //                 } else {
    //                     return acc;
    //                 }
    //             }, []);

    //             // console.log(uniqueData);
    //             let mergedJsonString = JSON.stringify(uniqueData);

    //             $.ajax({
    //                 url: "/api/addProposalValidation",
    //                 type: "POST",
    //                 contentType: "application/json",
    //                 data: mergedJsonString,
    //                 success: function (response) {
    //                     // console.log(response);
    //                     $("#selectIC").val("");
    //                     $("#journeytype").val("");
    //                     $("#maxengine").val("");
    //                     $("#minengine").val("");
    //                     $("#maxchassis").val("");
    //                     $("#minchassis").val("")
    //                     $("#regxengine").val("");
    //                     $("#regxchassis").val("")

    //                     $("#successMessage").fadeIn().delay(3000).fadeOut();
    //                     $("form :input").prop("disabled", false);
    //                     $("#submit").prop("disabled", false);
    //                     propic = null;
    //                     jtype = null;
    //                 },
    //                 error: function (xhr, status, error) {
    //                     console.error(xhr.responseText);
    //                     $("#failMessage").fadeIn().delay(3000).fadeOut();
    //                     $("form :input").prop("disabled", false);
    //                     $("#submit").prop("disabled", false);
    //                 }
    //             });

    //         }

    //     });

    // });

    $(document).ready(function () {

        var propic = "";
        var ic = "";
        var etic = "";
        var jtype = "";

        function sortic(data,ic) {
            if (data.data == null) {
                return null;
            }
            for( i=0 ; i < data.data.length ; i++){
                if (data.data[i].IcName == ic ){
                    // console.log(data.data[i].IcName);
                    return data.data[i];
                }
            }
        }

        function validateForm() {
            let isValid = true;
            if ( $("#selectIC").val() === "" ){
                $(".selectIcerror").fadeIn().delay(3000).fadeOut('slow');
                // alert("Select Ic");
                isValid = false;
            }
            if ( $("#journeytype").val() === ""  ){

                $(".selectJtypeerror").fadeIn().delay(3000).fadeOut('slow');
                // alert("Select Journey Type");
                isValid = false;
            }
            if (
                $("#maxengine").val() === "" ||
                $("#minengine").val() === "" ||
                $("#maxchassis").val() === "" ||
                $("#minchassis").val() === ""    ) {

                $(".minerror").fadeIn().delay(3000).fadeOut('slow');
                $(".maxerror").fadeIn().delay(3000).fadeOut('slow');
                // alert("Please fill all the fields");
                isValid = false;
            }

            if (
                parseFloat($("#maxengine").val()) < parseFloat($("#minengine").val()) ||
                parseFloat($("#maxchassis").val()) < parseFloat($("#minchassis").val())  ) {

                // alert("Minimum number inputted is greater than maximum number!");
                $(".inputerror").fadeIn().delay(3000).fadeOut('slow');
                isValid = false;
            }

            if ($("#regxengine").val().trim() !== '' && $("#engineRegxFailureMsg").val().trim() === '') {
                $(".engineRegxFailureMsg").fadeIn().delay(3000).fadeOut('slow');
                isValid = false;
            }

            if ($("#regxchassis").val().trim() !== '' && $("#chassisRegxFailureMsg").val().trim() === '') {
                $(".chassisRegxFailureMsg").fadeIn().delay(3000).fadeOut('slow');
                isValid = false;
            }

            return isValid;
        }

        function populateFormFields() {

            if (propic && propic.hasOwnProperty(jtype) ) {
                $("#minengine").val(propic[jtype].engineNomin);
                $("#maxengine").val(propic[jtype].engineNomax);
                $("#minchassis").val(propic[jtype].chasisNomin);
                $("#maxchassis").val(propic[jtype].chasisNomax);
                $("#regxengine").val(propic[jtype].regxengine);
                $("#regxchassis").val(propic[jtype].regxchassis);
                $("#engineRegxFailureMsg").val(propic[jtype].engineRegxFailureMsg);
                $("#chassisRegxFailureMsg").val(propic[jtype].chassisRegxFailureMsg);

            } else {
                // Clear the form fields if the journey type is not found in propic
                $("#minengine").val("");
                $("#maxengine").val("");
                $("#minchassis").val("");
                $("#maxchassis").val("");
                $("#regxengine").val("");
                $("#regxchassis").val("");
                $("#engineRegxFailureMsg").val("");
                $("#chassisRegxFailureMsg").val("");
            }
        }

        $("#reset").click(function () {

            $("#selectIC").val("");
            $("#journeytype").val("");
            $("#maxengine").val("");
            $("#minengine").val("");
            $("#maxchassis").val("");
            $("#minchassis").val("");
            $("#engineRegxFailureMsg").val("");
            $("#chassisRegxFailureMsg").val("");

        });

        $("#selectIC").change(function () {
            ic = $(this).val();
            jtype=null
            // Retrieve data for the selected IcName
            $.getJSON("{{route('api.getIcList')}}", {}, function (data) {
                $.getJSON("{{route('api.getProposalValidation')}}", {}, function (data) {
                    etic = data;
                    propic = sortic(data, ic);
                    populateFormFields();
                });
            });

            $("#journeytype").val("");
            $("#maxengine").val("");
            $("#minengine").val("");
            $("#maxchassis").val("");
            $("#minchassis").val("");
            $("#regxengine").val("");
            $("#regxchassis").val("");
            $("#engineRegxFailureMsg").val("");
            $("#chassisRegxFailureMsg").val("");

        });

        // Event listener for the change event of the journeytype element
        $("#journeytype").change(function () {
            jtype = $(this).val();

            if (jtype == ""){
                $("#journeytype").val("");
                $("#maxengine").val("");
                $("#minengine").val("");
                $("#maxchassis").val("");
                $("#minchassis").val("");
                $("#regxengine").val("");
                $("#regxchassis").val("");
                $("#engineRegxFailureMsg").val("");
                $("#chassisRegxFailureMsg").val("");

            }else {
                if (ic && jtype) {
                    populateFormFields(); // Call the function to populate form fields dynamically
                }
            }
        });

        $("#submit").click(function () {
            if (validateForm()) {

                let mergedJsonString
                let data1val = [];
                data1val = etic.data;
                // console.log(data1val);

                let data2val = {};
                data2val['IcName'] = $("#selectIC").val();
                data2val[$('#journeytype').val()] = {
                    engineNomax: $("#maxengine").val(),
                    engineNomin: $("#minengine").val(),
                    chasisNomax: $("#maxchassis").val(),
                    chasisNomin: $("#minchassis").val(),
                    regxengine: $("#regxengine").val(),
                    regxchassis: $("#regxchassis").val(),
                    engineRegxFailureMsg : $("#engineRegxFailureMsg").val(),
                    chassisRegxFailureMsg : $("#chassisRegxFailureMsg").val()
                };
                // console.log(data2val);
                if (ic === 'all') {

                    // Update or concatenate data2val for all IC entries
                    for (let i = 0; i < data1val.length; i++) {
                        if (data1val[i][jtype]) {
                            Object.assign(data1val[i][jtype], data2val[jtype]);
                        } else {
                            data1val[i][jtype] = Object.assign({}, data2val[jtype]);
                        }
                    }

                    mergedJsonString = JSON.stringify(data1val);

                } else {

                    if (data1val !== null) {
                        let existingIndex = -1;
                        data1val.forEach((entry, index) => {
                            if (entry['IcName'] === data2val['IcName']) {
                                existingIndex = index;
                                return false;
                            }
                        });

                        if (existingIndex !== -1) {
                            // Merge the data into the existing entry
                            Object.assign(data1val[existingIndex], data2val);
                        } else {
                            // Add data2val as a new entry in the array
                            data1val.push(data2val);
                        }


                        // Filter and keep unique entries based on 'IcName'
                        uniqueData = data1val.reduce((acc, current) => {
                            const x = acc.find(item => item.IcName === current.IcName);
                            if (!x) {
                                return acc.concat([current]);
                            } else {
                                return acc;
                            }
                        }, []);
                    } else {
                        uniqueData = [];
                        uniqueData.push(data2val);
                    }
                    // console.log(uniqueData);
                    mergedJsonString = JSON.stringify(uniqueData);
                }

                // console.log(data1val);
                // console.log(mergedJsonString);
                $.ajax({
                    url: "{{route('admin.addProposalValidation')}}",
                    type: "POST",
                    contentType: "application/json",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: mergedJsonString,
                    success: function (response) {
                        // console.log(response);
                        $("#selectIC").val("");
                        $("#journeytype").val("");
                        $("#maxengine").val("");
                        $("#minengine").val("");
                        $("#maxchassis").val("");
                        $("#minchassis").val("")
                        $("#regxengine").val("");
                        $("#regxchassis").val("")

                        $("#engineRegxFailureMsg").val("");
                        $("#chassisRegxFailureMsg").val("");
                        $("#successMessage").fadeIn().delay(5000).fadeOut();
                        $("form :input").prop("disabled", false);
                        $("#submit").prop("disabled", false);
                        propic = null;
                        jtype = null;
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        $("#failMessage").fadeIn().delay(5000).fadeOut();
                        $("form :input").prop("disabled", false);
                        $("#submit").prop("disabled", false);
                    }
                });

            }

        });

    });
</script>

@endsection
