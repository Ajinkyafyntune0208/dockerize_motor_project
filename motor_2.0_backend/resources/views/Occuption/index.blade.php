@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Master Occuption')])
@section('content')

<style>

    #rto_zone, #rto_status, #rto_state{
        background-color: #ffffff!important;
        color : #000000!important;
    }

    @media (min-width: 576px){
        .modal-dialog {
            max-width: 911px;
            margin: 34px auto;
            word-wrap: break-word;
        }
    }

</style>


<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Master Occupation
                        <a href="#" class="view btn btn-primary float-end btn-sm" target="_blank" data-bs-toggle="modal" data-bs-target="#rtoModal" data="">Insert New Occupation</a>
                    </h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped" id="response_log">
                            <thead>
                                <tr>
                                    <th scope="col">Sr. No.</th>
                                    <th scope="col">Occupation Code</th>
                                    <th scope="col">Occuptaion Name</th>
                                    <th scope="col">Company Alias</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($occuption as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $data->occupation_code }}</td>
                                    <td>{{ $data->occupation_name }}</td>
                                    <td>{{ $data->company_alias }}</td>
                                    <td><a href="#" class="view text-dark" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal" data="{{ $data }}"><i style="font-size: 1.2rem;" class="mdi mdi-grease-pencil"></i></a></td>
                                    <td>
                                    <form action="{{ route('admin.master-occuption.destroy', $data->occupation_id) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> Edit Occuption <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">

                        <form action="{{ route('admin.master-occuption.update', [1, 'data' => request()->all()]) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <input type="text" name="id" id="id"  hidden/>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="occupation_code" class="required">Occuption Code</label>
                                        <input type="text" class="form-control" name="occupation_code" id="occupation_code" required/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="occupation_name" class="required">Occuption Name</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="occupation_name1" id="occupation_name1" style="background-color: white;color: #404040;" required disabled>
                                        @foreach($occuption as $key => $data)
                                            <option value="{{ $data->occupation_name }}">{{$data->occupation_name }}</option>
                                        @endforeach 
                                    </select>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="company_alias" class="required">Company Alias</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="company_alias1" id="company_alias1" style="background-color: white;color: #404040;" required disabled>
                                            @foreach($company as $key => $datas)
                                                <option value="{{ $datas->company_alias}}">{{$datas->company_alias}}</option>
                                            @endforeach 
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                                    <button type="submit" id="update-rto-btn" class="btn btn-primary w-100">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                {{-- <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div> --}}

        </div>
    </div>
</div>


{{-- Inssert New Occuption--}}
<div class="modal fade" id="rtoModal" tabindex="-1" aria-labelledby="rtoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rtoModalLabel"> Insert New Occupation <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.master-occuption.store')}}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="occupation_code" class="required">Occuptaion Code</label>
                                <input type="text" class="form-control" name="occupation_code" id="occupation_code" required/>
                            </div>
                        </div>
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="occupation_name" class="required">Occupation Name</label>
                                <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="occupation_name" id="occupation_name" style="background-color: white;color: #404040;" required>
                                    @foreach($Destinctoccuption as $key => $data)
                                        <option value="{{ $data->occupation_name }}">{{$data->occupation_name }}</option>
                                    @endforeach 
                                </select>
                            </div>
                        </div>
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="company_alias" class="required">Company Alias</label>
                                <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="company_alias" id="company_alias" style="background-color: white;color: #404040;" required>
                                    @foreach($company as $key => $datas)
                                        <option value="{{ $datas->company_alias}}">{{$datas->company_alias}}</option>
                                    @endforeach 
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                            <button type="submit" id="update-rto-btn" class="btn btn-primary w-100">Save</button>
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

@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        $('#response_log').DataTable({
            "initComplete" : function(){ //column wise filter
                var notApplyFilterOnColumn = [0, 3, 4, 5, 6];
					var inputFilterOnColumn = [];
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
							$('td.gtp-dt-select-filter-' + index).html('<input type="text" class="gtp-dt-input-filter">');
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

			                        column
			                            .search( val ? '^'+val+'$' : '', true, false )
			                            .draw();
			                    } );
                                $('td.gtp-dt-select-filter-' + index).html(select);
			                column.data().unique().sort().each( function ( d, j ) {
			                    select.append( '<option value="'+d+'">'+d+'</option>' )
			                } );
						}
					});
            }
        });

        // Ajax call for State

        var type = "GET";
        var backend_url = window.location.origin;

        $('#rto_state').empty();

        $.ajax({
            type: type,
            url: backend_url + '/api/get_state',
            dataType: 'json',
            success: function (data) {
                var options =  '<option value="" selected disabled><strong>Select State</strong></option>';
                if (data.state_name != '') {
                    data.forEach(element => {
                        options += '<option value="'+element.state_id+'">'+element.state_name+'</option>';
                    });
                }else{
                    options =  '<option value="" selected disabled><strong>State Not Available</strong></option>';
                }
                $('#rto_state').append(options);
            },
            error: function (data) {
                console.log(data);
            }
        });

        // Ajax call for Zone

        $('#rto_zone').empty();

        $.ajax({
            type: type,
            url: backend_url + '/api/get_zone',
            dataType: 'json',
            success: function (data) {
                var options =  '<option value="" selected disabled><strong>Select Zone</strong></option>';
                if (data.zone_name != '') {
                    data.forEach(element => {
                        options += '<option value="'+element.zone_id+'">'+element.zone_name+'</option>';
                    });
                }else{
                    options =  '<option value="" selected disabled><strong>Zone Not Available</strong></option>';
                }
                $('#rto_zone').append(options);
            },
            error: function (data) {
                console.log(data);
            }
        });

    });

    $(document).on('click', '.view', function () {
        var data = JSON.parse($(this).attr('data'));

        $('#exampleModalLabel').html(`Edit ${data.occupation_name} Occuption Details:`);

        $("#occupation_code").val(data.occupation_code);

        $('select[name="occupation_name1"]').find('option[value="'+data.occupation_name+'"]').attr("selected",true);
        $('select[name="company_alias1"]').find('option[value="'+data.company_alias+'"]').attr("selected",true);

        // update button dynamic name
        $('#update-rto-btn').html(`Update ${data.occupation_name} Occuption`);
        

        $('#id').val(data.occupation_id );

        $('#showdata').html(data);
    });

    setTimeout(() => {
        $('.alert-success').css('display', 'none');
    }, 2000);

    $(document).on('click', '.view', function () {
        var data = JSON.parse($(this).attr('data'));

        $('#exampleModaldeleteLabel').html(`Delete ${data.occupation_name} Occuption Details:`);

        // update button dynamic name
        $('#delete-rto-btn').html(`Delete ${data.occupation_name} Occuption`);

        $('#id').val(data.occupation_id );

        $('#showdata').html(data);
    });

    setTimeout(() => {
        $('.alert-success').css('display', 'none');
    }, 2000);

</script>
@endpush
