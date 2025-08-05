@extends('admin_lte.layout.app', ['activePage' => 'vahan', 'titlePage' => __('vahan')])
@section('content')
    <style>
        select {
  text-align: center;
  text-align-last: center;
  /* webkit*/
}
.foo
{
    padding-left: 346px;
    padding-bottom: 15px;
}
option {
  text-align: center;
  /* reset to left*/
}
        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
            border-radius: 8px;
        }

        .my-table {
            word-wrap: break-word;
            table-layout: fixed;
            width: 100%;
        }

        input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
        }

        table,
        th,
        td {
            border: 1.4px solid black;
        }

        .table td {
            text-align: center;
        }

        .table {
            margin-left: auto;
            margin-right: auto;
        }
        .setalign{
            margin-left: -6px;
        }
    </style>
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ 'Configuration of ' . $v_type }}</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.vahan_service_save.prioritySave', $v_type) }}" method="POST" class="mt-3" name="add_service_priority" onsubmit="return func(this);">
                            @csrf @method('POST')
                            <div class="row mb-4">

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Journey stage</label>
                                        <input id="j_stage" name="j_stage" type="text" value="{{ $j_val }}"
                                            class="form-control select2" placeholder="" readonly>
                                        @error('url')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Integration</label>
                                        <select name="intergration" id="intergration" class="form-control select2" aria-label=".form-select-sm example" onchange="hidePriority()">
                                            <option
                                                {{ sizeof($vahan_configs) != 0 && $vahan_configs[0]->integration_process == 'priority' ? 'selected' : '' }}
                                                value="priority">Priority</option>
                                            {{-- <option
                                                {{ sizeof($vahan_configs) != 0 && $vahan_configs[0]->integration_process == 'parallel' ? 'selected' : '' }}
                                                value="parallel">Parallel</option> --}}
                                            <option
                                                {{ sizeof($vahan_configs) != 0 && $vahan_configs[0]->integration_process == 'single' ? 'selected' : '' }}
                                                value="single">Single</option>    
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-6" id="prio_service1">
                                    <div class="form-group">
                                        <label>Services</label>
                                        @foreach ($vahan_Services as $key => $vahan_Service)
                                            <select id="services" name={{ 'service_' . $vahan_Service->id }} class="form-control select2 mb-3" aria-label=".form-select-sm example">
                                                <option value={{ $vahan_Service->id }}>
                                                    {{ $vahan_Service->vahan_service_name }}</option>
                                            </select>
                                        @endforeach
                                    </div>
                                </div>
                                {{-- <div class="col-sm-6" id="single_service"> --}}
                                    <div class="form-group" id="single_service">
                                        <label>Services</label>
                                            <select id="single_service" name="single_service" class="form-control select2 "  style="width:1039px" aria-label=".form-select-sm example">
                                                @foreach ($vahan_Services as $key => $vahan_Service)
                                                <option {{ sizeof($vahan_configs) != 0 && $vahan_configs[0]->vahan_service_id == $vahan_Service->id ? 'selected' : '' }} value={{ $vahan_Service->id }}>
                                                    {{ $vahan_Service->vahan_service_name }}</option>
                                                @endforeach
                                            </select>
                                    </div>
                                
                                <div class="col-sm-6" id="prio_service2">
                                    <div class="form-group">
                                        <label>Priority</label>
                                        @foreach ($collection as $key => $vahan_Service)
                                            <select id={{ 'select_' . $vahan_Service['id'] }}
                                                name={{ 'prio_no_' . $vahan_Service['id'] }} class="form-control select2 mb-3" aria-label=".form-select-sm example">
                                                <option value=''></option>
                                                @foreach ($collection as $no => $list)
                                                    @php
                                                        $priority_no = $vahan_Service['priority_no'] ?? '';
                                                        $display = $value = $no+1;
                                                        if ($priority_no === 0 && !isset($is_not_required_set)) {
                                                            $is_not_required_set = true;
                                                        }
                                                    @endphp
                                                    <option {{$priority_no == $value ? "selected" : ""}} value={{ $value }}>{{ $display }}</option>
                                                @endforeach
                                                <option value='0' {{($is_not_required_set ?? false) ? 'selected' : ''}}>Not Required</option>
                                                @php
                                                    unset($is_not_required_set);
                                                @endphp
                                            </select>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-sm-12 col-centered">
                                    <label class="foo"> Select all scenarios
                                    <input type="checkbox" id="masterCheck" name="masterCheck" class="selectall"></label>
                                </div>
                                <div class="col-sm-3">
                                    <h6 class="text-center" {{ ($j_val == 'Input page') ? 'hidden' :'' }}>B2b pos</h6>
                                    <label>{{ ($j_val == 'Input page') ? 'B2b Pos' : 'Select all' }}</label>
                                    <input type="checkbox" {{ (sizeof($vahan_configs) != 0 && $vahan_configs[0]->B2B_pos_decision == 'true') ? 'checked' : '' }} id="pos_check" name="pos_check" onclick="uncheckAll('div_pos_','pos_check','pos_block_')">
                                    
                                    <label id="pos_hid" style="float: right;">Failure Block
                                    <input type="checkbox" class="fblock" id="pos_block" name="pos_block" onclick="blockcheckAll('pos_block','pos_')">
                                </label>
                                    <div  id="div_pos">
                                    @foreach ($Companies as $key => $value)
                                        <div class="input-group mb-3">
                                            <div class="input-group-text div_pos_">
                                                
                                                <input class="form-check-input mt-0 ml-n2 abc"
                                                    {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_pos_decision)->allowedICs ?? ''))) ? 'checked' : ' ' }}
                                                    type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("pos_{{$value->company_id}}","pos_block{{$value->company_id}}")'
                                                    name={{ 'pos_' . $value->company_id }} id={{ 'pos_' . $value->company_id }}
                                                    aria-label="Checkbox for following text input">
                                            </div>
                                            <input type="text" value={{ $value->company_shortname }}
                                                class="form-control" aria-label="Text input with checkbox" readonly>
                                                <div class="input-group-text pos_block_ abc">
                                                    <input class="form-check-input mt-0 setalign"
                                                    {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_pos_decision)->blockFailure ?? ''))) ? 'checked' : 'disabled' }}
                                                        type="checkbox" value={{ $value->company_id }} 
                                                        name={{ 'pos_block' . $value->company_id }} id={{ 'pos_block' . $value->company_id }}
                                                        aria-label="Checkbox for following text input">
                                                </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <h6 class="text-center" {{ ($j_val == 'Input page') ? 'hidden' :'' }}>B2b Employee</h6>
                                    <label>{{ ($j_val == 'Input page') ? 'B2b Employee' : 'Select all' }}</label>
                                    <input type="checkbox" {{ (sizeof($vahan_configs) != 0 && $vahan_configs[0]->B2B_employee_decision == 'true') ? 'checked' : '' }} id="emp_check" name="emp_check" onclick="uncheckAll('div_emp_','emp_check','emp_block_')">
                                    <label id="emp_hid" style="float: right;">Failure Block
                                        <input type="checkbox"  id="emp_block" name="emp_block" onclick="blockcheckAll('emp_block','emp_')">
                                    </label>
                                    <div  id="div_emp">
                                    @foreach ($Companies as $key => $value)
                                        <div class="input-group mb-3">
                                            <div class="input-group-text div_emp_">
                                                <input class="form-check-input mt-0 setalign"
                                                {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_employee_decision)->allowedICs ?? ''))) ? 'checked' : ' ' }} type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("emp_{{$value->company_id}}","emp_block{{$value->company_id}}")'
                                                    name={{ 'emp_' . $value->company_id }}  id={{ 'emp_' . $value->company_id }}
                                                    aria-label="Checkbox for following text input">
                                            </div>
                                            <input type="text" value={{ $value->company_shortname }}
                                                class="form-control" aria-label="Text input with checkbox" readonly>
                                                <div class="input-group-text emp_block_">
                                                    <input class="form-check-input mt-0 setalign"
                                                    {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_employee_decision)->blockFailure ?? ''))) ? 'checked' : 'disabled' }}  type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("emp_{{$value->company_id}}","emp_block{{$value->company_id}}")'
                                                    type="checkbox" value={{ $value->company_id }}
                                                        name={{ 'emp_block' . $value->company_id }} id={{ 'emp_block' . $value->company_id }}
                                                        aria-label="Checkbox for following text input">
                                                </div>
                                        </div>
                                    @endforeach
                                </div>
                                </div>
                                <div class="col-sm-3">
                                    <h6 class="text-center" {{ ($j_val == 'Input page') ? 'hidden' :'' }}>B2b Partner</h6>
                                    <label>{{ ($j_val == 'Input page') ? 'B2b Partner' : 'Select all' }}</label>
                                    <input type="checkbox" {{ (sizeof($vahan_configs) != 0 && $vahan_configs[0]->B2B_partner_decision == 'true') ? 'checked' : '' }} id="partner_check" name="partner_check" onclick="uncheckAll('div_partner_','partner_check','partner_block_')">
                                    <label id="part_hid" style="float: right;">Failure Block
                                        <input type="checkbox"  id="partner_block" name="partner_block" onclick="blockcheckAll('partner_block','partner_')">
                                    </label>
                                    <div  id="div_partner">
                                    @foreach ($Companies as $key => $value)
                                        <div class="input-group mb-3">
                                            <div class="input-group-text div_partner_">
                                                <input class="form-check-input mt-0 setalign"
                                                {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_partner_decision)->allowedICs ?? ''))) ? 'checked' : ' ' }}  type="checkbox" value={{ $value->company_id }}
                                                    type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("partner_{{$value->company_id}}","partner_block{{$value->company_id}}")'
                                                    name={{ 'partner_' . $value->company_id }} id={{ 'partner_' . $value->company_id }}
                                                    aria-label="Checkbox for following text input">
                                            </div>
                                            <input type="text" value={{ $value->company_shortname }}
                                                class="form-control" aria-label="Text input with checkbox" readonly>
                                                <div class="input-group-text partner_block_">
                                                    <input class="form-check-input mt-0 setalign"
                                                    {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2B_partner_decision)->blockFailure ?? ''))) ? 'checked' : 'disabled' }} type="checkbox" value={{ $value->company_id }}
                                                    type="checkbox" value={{ $value->company_id }}
                                                        name={{ 'partner_block' . $value->company_id }} id={{ 'partner_block' . $value->company_id }}
                                                        aria-label="Checkbox for following text input">
                                                </div>
                                        </div>
                                    @endforeach
                                </div>
                                </div>
                                <div class="col-sm-3">
                                    <h6 class="text-center" {{ ($j_val == 'Input page') ? 'hidden' :'' }}>B2c</h6>
                                    <label>{{ ($j_val == 'Input page') ? 'B2c' : 'Select all' }}</label>
                                    <input type="checkbox" {{ (sizeof($vahan_configs) != 0 && $vahan_configs[0]->B2C_decision == 'true') ? 'checked' : '' }} id="b2c_check" name="b2c_check" onclick="uncheckAll('div_b2c_','b2c_check','b2c_block_')">
                                    <label id="b2c_hid" style="float: right;">Failure Block
                                        <input type="checkbox"  id="b2c_block" name="b2c_block" onclick="blockcheckAll('b2c_block','b2c_')">
                                    </label>
                                    <div  id="div_b2c">
                                    @foreach ($Companies as $key => $value)
                                        <div class="input-group mb-3">
                                            <div class="input-group-text div_b2c_">
                                                <input class="form-check-input mt-0 setalign"
                                                {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2C_decision)->allowedICs ?? ''))) ? 'checked' : ' ' }}  type="checkbox" value={{ $value->company_id }} 
                                                    type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("b2c_{{$value->company_id}}","b2c_block{{$value->company_id}}")'
                                                    name={{ 'b2c_' . $value->company_id }} id={{ 'b2c_' . $value->company_id }}
                                                    aria-label="Checkbox for following text input">
                                            </div>
                                            <input type="text" value={{ $value->company_shortname }}
                                                class="form-control" aria-label="Text input with checkbox" readonly>
                                                <div class="input-group-text b2c_block_">
                                                    <input class="form-check-input mt-0 setalign"
                                                    {{ sizeof($vahan_configs) != 0 && in_array($value->company_id, explode(',', (json_decode($vahan_configs[0]->B2C_decision)->blockFailure ?? ''))) ? 'checked' : 'disabled' }} type="checkbox" value={{ $value->company_id }} onclick='uncheckblk("emp_{{$value->company_id}}","emp_block{{$value->company_id}}")'
                                                    type="checkbox" value={{ $value->company_id }}
                                                        name={{ 'b2c_block' . $value->company_id }} id={{ 'b2c_block' . $value->company_id }}
                                                        aria-label="Checkbox for following text input">
                                                </div>
                                        </div>
                                    @endforeach
                                </div>
                                </div>
                                <div class="col-sm-12 text-right">
                                    <div class="d-flex flex-row-reverse">
                                              <input type="hidden" id="array_ids" name="array_ids" class="form-control" value={{ $string_ids }}>
                                              <input type="hidden" id="ic_ids" name="ic_ids" class="form-control" value={{ $ic_ids }}>
                                        <input type="submit" class="btn btn-outline-primary" value="Submit">

                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    @endsection
    @section('scripts')
        <script>
            $(document).ready(function() {
    var j_stage=document.getElementById("j_stage").value;
    if(j_stage == 'Input page'){
        document.getElementById("pos_hid").style.display = 'none';
        document.getElementById("emp_hid").style.display = 'none';
        document.getElementById("part_hid").style.display = 'none';
        document.getElementById("b2c_hid").style.display = 'none';
        $("#div_b2c").hide();
        $("#div_partner").hide();
        $("#div_pos").hide();
        $("#div_emp").hide();
    }else{
        document.getElementById("pos_hid").style.display = 'block';
        document.getElementById("emp_hid").style.display = 'block';
        document.getElementById("part_hid").style.display = 'block';
        document.getElementById("b2c_hid").style.display = 'block';
        $("#div_b2c").show();
        $("#div_partner").show();
        $("#div_pos").show();
        $("#div_emp").show();
    }
    hidePriority();
    checkAllBoxes();
});
            function func(e)
            {
                var intergration=document.getElementById("intergration").value;
                var prio=document.getElementById('array_ids').value;
                
                var arrays = prio.split(',');
                var isValid = true;
                arrays.forEach(async (array) => {
                    var select=document.getElementById('select_'+array).value;
                    if(select == '' || select == null){
                       
                        isValid = false;
                        return false;   
                    }
                   
                   });
                   if (intergration == 'priority'){
                   if(!isValid){
                        alert('Select priority for each services');
                        return false;
                   }
                }
                e.submit();
                return true;
             }
            $(document).on('change', '[id^="select_"]', function() {
                var dropdown_value = $(this).val();
                var dropdown_id = $(this).attr('id');
                if ( !['', '0', 0].includes(dropdown_value) ) {
                    $('[id^="select_"]:not([id="' + dropdown_id + '"])').find("option[value='" + dropdown_value + "']").attr('disabled', true);
                }
                $('[id^="select_"]:not([id="' + dropdown_id + '"])').find("option[value!='" + dropdown_value + "']:disabled").each(function() {
                    dropdown_value = $(this).val();
                    dropdown_id = $(this).parent().attr('id');

                    if ($('[id^="select_"]:not([id="' + dropdown_id + '"])').find("option[value='" + dropdown_value + "']:selected").length == 0) {
                        $(this).removeAttr('disabled');
                    }
                });
            });

 $('.selectall').click(function () {
    var e1 = 'pos_block_';
    var e2 = 'b2c_block_';
    var e3 = 'partner_block_';
    var e4 = 'emp_block_';
    if ($(this).prop('checked')) {
    $('input').prop('checked', true);
    $('.' + e1 + ' :checkbox').prop('disabled', false);
    $('.' + e2 + ' :checkbox').prop('disabled', false);
    $('.' + e3 + ' :checkbox').prop('disabled', false);
    $('.' + e4 + ' :checkbox').prop('disabled', false);
    }
    else 
    {
    $('input').prop('checked', false);
    $('.' + e1 + ' :checkbox').prop('disabled', true);
    $('.' + e2 + ' :checkbox').prop('disabled', true);
    $('.' + e3 + ' :checkbox').prop('disabled', true);
    $('.' + e4 + ' :checkbox').prop('disabled', true);
    }
    });
    $('.selectall').trigger('change');       
function uncheckAll(divid,checkBoxId,block) {
    var check = document.getElementById(checkBoxId).checked;
    if (check) {
        $('.' + divid + ' :checkbox').prop('checked', true);
        $('.' + block + ' :checkbox').prop('disabled', false);
    } else {
        $('.' + divid + ' :checkbox').prop('checked', false);
        $('.' + block + ' :checkbox').prop('checked', false);
        $('.' + block + ' :checkbox').prop('disabled', true);
    }
}
function uncheckblk(I_id,block){
    var checked=document.getElementById(I_id).checked;
    var element = document.getElementById(block);
    if(checked){
        element.disabled = false;
    }else{
        element.disabled = true;
        document.getElementById(block).checked = false;
    } 
}
function blockcheckAll(checkBoxId,check_id){
     var checked = document.getElementById(checkBoxId).checked;
    var ic=document.getElementById('ic_ids').value;   
                var arrays = ic.split(',');
                arrays.forEach(async (array) => {
                    var element = document.getElementById(checkBoxId+array);
                    var select=document.getElementById(check_id+array).checked;
                    if(!checked)
                {
                    element.disabled = true;
                    document.getElementById(checkBoxId+array).checked = false; 
                }else{
                    if(select){
                        element.disabled = false;
                        document.getElementById(checkBoxId+array).checked = true;   
                    }
                }
                   
                   });
}

function hidePriority() {
    var intergration=document.getElementById("intergration").value;  
    if (intergration == 'single'){
        $("#single_service").show();
        $("#prio_service1").hide();
        $("#prio_service2").hide(); 
    }else if (intergration == 'parallel'){
        $("#prio_service1").hide();
        $("#prio_service2").hide();
        $("#single_service").hide();
    }else{
   $("#prio_service1").show();
   $("#prio_service2").show();
   $("#single_service").hide();
    }
}

function checkAllBoxes() {
    let pos = document.querySelectorAll('.div_pos_ input[type=checkbox]')
    let allChecked1 = [...pos].every(checkbox => checkbox.checked)
    
    if (allChecked1) {
       document.querySelector('[name="pos_check"]').checked=true
    }

    let posFailure = document.querySelectorAll('.pos_block_ input[type=checkbox]')
    allChecked2 = [...posFailure].every(checkbox => checkbox.checked)
    
    if (allChecked2) {
        document.querySelector('[name="pos_block"]').checked=true
    }

    let employee = document.querySelectorAll('.div_emp_ input[type=checkbox]')
    allChecked3 = [...employee].every(checkbox => checkbox.checked)
    
    if (allChecked3) {
        document.querySelector('[name="emp_check"]').checked=true
    }

    let employeeFailure = document.querySelectorAll('.emp_block_ input[type=checkbox]')
    allChecked4 = [...employeeFailure].every(checkbox => checkbox.checked)
    
    if (allChecked4) {
        document.querySelector('[name="emp_block"]').checked=true
    }

    let partner = document.querySelectorAll('.div_partner_ input[type=checkbox]')
    allChecked5 = [...partner].every(checkbox => checkbox.checked)
    
    if (allChecked5) {
        document.querySelector('[name="partner_check"]').checked=true
    }

    let partnerFailure = document.querySelectorAll('.partner_block_ input[type=checkbox]')
    allChecked6 = [...partnerFailure].every(checkbox => checkbox.checked)
    
    if (allChecked6) {
        document.querySelector('[name="partner_block"]').checked=true
    }

    let b2c = document.querySelectorAll('.div_b2c_ input[type=checkbox]')
    allChecked7 = [...b2c].every(checkbox => checkbox.checked)
    
    if (allChecked7) {
        document.querySelector('[name="b2c_check"]').checked=true
    }

    let b2cFailure = document.querySelectorAll('.b2c_block_ input[type=checkbox]')
    allChecked8 = [...b2cFailure].every(checkbox => checkbox.checked)
    
    if (allChecked8) {
        document.querySelector('[name="b2c_block"]').checked=true
    }
    if(allChecked1 && allChecked2 && allChecked3 && allChecked4 && allChecked5 && allChecked6 && allChecked7 && allChecked8){
        document.querySelector('[name="masterCheck"]').checked=true
    }
    var stage = document.getElementById("j_stage").value;
    var all1 = document.getElementById("b2c_check").checked;
    var all2 = document.getElementById("partner_check").checked;
    var all3 = document.getElementById("emp_check").checked;
    var all4 = document.getElementById("pos_check").checked;
    if(stage=='Input page' && all1 && all2 && all3 && all4){
        document.querySelector('[name="masterCheck"]').checked=true
    }
}
        </script>
@endsection('scripts')
