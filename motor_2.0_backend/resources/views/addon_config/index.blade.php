@extends('layout.app', ['activePage' => 'addon-config', 'titlePage' => __('Addon Configuration')])

@section('content')
<style>
    @media (min-width: 576px){
        .modal-dialog {
             max-width: 744px; 
            margin: 34px auto;
            word-wrap: break-word;
        }
    }
</style>
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Addon Configuration</h5>
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif
              
              
               <div class="row mt-4">
                <div class="col-12 d-flex justify-content-end stretch-card">
                    
                </div>
            </div>
            <a href="#" class="btn btn-primary btn-sm updateall" data-bs-toggle="modal" company="{{old('company_name',request()->company_name)}}" section="{{old('section',request()->section)}}" data-bs-target="#exampleModal1" >Edit All</a>
            <a href="{{ route('admin.addon-config.index') }}" class="btn btn-primary btn-sm" style="margin-left: 95%;">Back</a>
            <form action="" method="get">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Company Name</label>
                    <select name="company_name" id="company_name" data-style="btn-primary btn-sm" required class="selectpicker w-100" data-live-search="true">
                        <option value="">Nothing selected</option>
                        
                        @foreach($company as $key => $comp)
                        <option {{ old('company_name',request()->company_name) == $comp->company_alias ? 'selected' : '' }} value="{{ $comp->company_alias }}">{{ $comp->company_alias }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Section</label>
                    <select name="section" id="section" data-style="btn-primary btn-sm" required class="selectpicker w-100" data-live-search="true">
                        <option value="">Nothing selected</option>
                        @foreach($section as $key => $section)
                        <option {{ old('section',request()->section) == $section ? 'selected' : '' }} value="{{ $section }}" > {{ $section }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><br>
                    <button class="btn btn-success btn-sm" style=" margin-top: 8px;" >Search</button>
                </div>
            </div>
        </div>
            </form>
                <div class="table-responsive">
                    <table class="table table-striped" id="push_data_api_table">
                        <thead>
                            <tr>
                                <th scope="col">Addons</th>
                                <th scope="col">age company wise</th> 
                                <th scope="col">Action</th> 
                            </tr>
                        </thead>
                        <tbody>
                        @empty($addon)  

                        @else 
                           @foreach($addon as $add)
                           <tr>
                          <td>{{$add->addon}}</td> 
                          <td>
                          @if($add->addon_age =='1')

                                {{"Applicable"}}

                                @elseif($add->addon_age =='0')

                                {{"Not Applicable"}}

                                @else

                                {{$add->addon_age}}

                                @endif
                          </td>
                          <td>
                          <button type="button" class="btn btn-success btn-sm update_addon" data="{{$add->addon_age}}" iddata="{{$add->id}}" cname="{{$company_name}}" section="{{$section}}" data-bs-toggle="modal" addon_name="{{$add->addon}}" data-bs-target="#exampleModal"><i class="fa fa-edit"></i></button>
                          </td>
                          </tr>
                           @endforeach
                           @endempty
                      
                        
                        </tbody>
                    </table>
                </div>
                
            </div>
        </div>
    </section>
</main>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><span id="cname"></span> Add-On Age </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    Addon Name -   <span id="addon_name"></span><br><br>
                    <form action="{{ route('admin.addon-config.update',[1, 'data' => request()->all()])}}" method="post" id="submitform">@csrf @method('PUT')
                    <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <input type="hidden" name="id" id="id" value="">
                                <input type="hidden" name="type" id="type" value="">
                                <label>Company Name</label>
                                <input type="text" class="form-control" name="company" id="company" value="" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>select Addon Age</label>
                                <select name="age" id="age" data-style="btn-primary" style="background-color: #ffffff!important;color : #000000!important;" required class="form-control" >
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                               <button class="btn btn-success btn-sm" style="margin-top: 31px;" id="update">submit</button>
                            </div>
                        </div>
                    </div>
                    </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>
            
        </div>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="exampleModal1" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><span id="cname"></span> Add-On Age </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    Company Name -   <span id="c_name"></span><br><br>
                    <form action="{{ route('admin.addon-config.update',[1, 'data' => request()->all()])}}" method="post" id="submitform">@csrf @method('PUT')
                    <div class="form-group">
                        <input type="hidden" id="ucname" name="company_name">
                        <input type="hidden" id="usection" name="section">
                        <input type="hidden"  name="muliupdate" value="true">
                    <table class="table table-striped">
                        <tr>
                            <thead>
                            <th>Addon Name</th>
                            <th>Addon Age</th>
                            </thead>
                        </tr>
                        <tbody id="addon">

                        </tbody>
                        
                    </table>
                    <br>
                    <button class="btn btn-success btn-sm float-right">Update</button>
                    </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>
            
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#push_data_api_table').DataTable({
            "pageLength": 25,
            "initComplete" : function(){ //column wise filter
                var notApplyFilterOnColumn = [1,2];
					var inputFilterOnColumn = [0];
					var showFilterBox = 'afterHeading'; //beforeHeading, afterHeading
					$('.gtp-dt-filter-row').remove();
					var theadSecondRow = '<tr class="gtp-dt-filter-row">';
					$(this).find('thead tr th').each(function(index){
						theadSecondRow += '<td class="gtp-dt-select-filter-' + index + '"></td>';
					});
					theadSecondRow += '</tr>';

					if(showFilterBox === 'beforeHeading'){
						$(this).find('thead').prepend(theadSecondRow);
					}else if(showFilterBox === 'afterHeading'){
						$(theadSecondRow).insertAfter($(this).find('thead tr'));
					}
                    
                    this.api().columns().every( function (index) {
						var column = this;
                        if(inputFilterOnColumn.indexOf(index) >= 0 && notApplyFilterOnColumn.indexOf(index) < 0){
							$('td.gtp-dt-select-filter-' + index).html('<input type="text" placeholder="Search" class="gtp-dt-input-filter">');
			                $( 'td input.gtp-dt-input-filter').on( 'keyup change clear', function () {
			                    if ( column.search() !== this.value ) {
			                        column
			                            .search( this.value )
			                            .draw();
			                    }
			                } );
						}else if(notApplyFilterOnColumn.indexOf(index) < 0){
							var select = $('<select><option value="">Select</option></select>')
			                    .on( 'change', function () {
                                        var val = $.fn.dataTable.util.escapeRegex(
                                            $(this).val()
                                        );
                                        alert(val);
                                        var urlpath = window.location.origin;
                                        alert(urlpath);
                                        $.ajax({
                                            method:"get",
                                            url:urlpath +"/admin/addon-config/show",
                                            data:{val:val}
                                        });
			                        column
			                            .search( val ? '^'+val+'$' : '', true, false )
			                            .draw();
			                    } );
                                $('td.gtp-dt-select-filter-' + index).html(select);
			                
			                    select.append( '<option value="godigit">godigit</option>' )
			                    
			                
						}
					});
            }
        });
    });
</script>
<script>
    $(document).on('click', '.update_addon', function () {
        $("#age").empty();
        var addon_age = $(this).attr('data');
        var id = $(this).attr('iddata');
        var cname = $(this).attr('cname');
        var section = $(this).attr('section');
        var name = $(this).attr('addon_name');
        $('#cname').html(cname);
        $('#addon_name').html(name);
        $('#company').val(cname);
        $('#id').val(id);
        $('#type').val(section);
        var option = `<option value="">Select addon Age</option>`;
        for(var i=0;i< 15;i++)
        {
            option += `<option value="${i}">${i}</option>`;
        }
        $("#age").append(option);
        $("#age").val(addon_age);
        });
</script>
<script>
    $('.updateall').on('click',function(){
    var company = $(this).attr('company');
    var type = $(this).attr('section');
    var urlpath = window.location.origin;
    $.ajax({
        method:"get",
        url:urlpath +"/admin/addon-config/show",
        data:{company:company,type:type},
        success:function(data)
        {
            var j = data;
            var row = '';
            $("#addon").empty();
            for (var i=0; i<j.addon.length; i++)
            {
                row += ('<tr><td><input type="text" name="addon[]" readonly value="' + j.addon[i].addon+ '"><br></td><td><input type="text" name="age['+j.addon[i].id+']" value="' + j.addon[i].addon_age+ '"><br></tr>')
            }
            $("#addon").append(row);
            $("#c_name").text(j.company);
            $("#ucname").val(j.company);
            $("#usection").val(j.section);
        },
        error:function(data)
        {

        }
    });
    });
</script>
<!-- <script>
    $('#submitform').submit(function(){
        var datastring = $("#submitform").serialize();
        var urlpath = window.location.origin;
        alert(datastring);
        $.ajax({
            method: "post",
            url: urlpath +"/admin/addon-config/update",
            data: datastring,
            success:function(response)
            {

            },
            error:function(response)
            {

            }
        });
    });
</script> -->
@endpush