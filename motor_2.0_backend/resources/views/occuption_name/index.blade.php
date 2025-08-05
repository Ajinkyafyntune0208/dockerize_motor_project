@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Occuption Name')])
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
                    <h5 class="card-title">Master Occupation Name
                        <a href="#" class="view btn btn-primary float-end btn-sm" target="_blank" data-bs-toggle="modal" data-bs-target="#rtoModal" data="">Insert New Occupation Name</a>
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
                                    <th scope="col">Occupation Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($occuption as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $data->occupation_name }}</td>
                                    <td><a href="#" class="view text-dark" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal" data="{{ $data }}"><i style="font-size: 1.2rem;" class="mdi mdi-grease-pencil"></i></a></td>
                                    <td>
                                        <form action="{{ route('admin.master-occupation-name.destroy', $data->id) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
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
                <h5 class="modal-title" id="exampleModalLabel"> Edit Occupation Name <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">

                        <form action="{{ route('admin.master-occupation-name.update', [1, 'data' => request()->all()]) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <input type="text" name="id" id="id"  hidden/>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="occupation_name" class="required">Name</label>
                                        <input type="text" class="form-control" name="occupation_name" id="occupation_name" required/>
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
                <h5 class="modal-title" id="rtoModalLabel"> Insert Occupation Name <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.master-occupation-name.store')}}" method="post">
                    @csrf
                    @method('POST')
                    <div class="row">
                        <input type="text" name="id" id="id"  hidden/>
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="occupation_name" class="required">Name</label>
                                <input type="text" class="form-control" name="occupation_name" id="occupation_name" required/>
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

        $('#exampleModalLabel').html(`Edit ${data.occupation_name} Occuption Name Details:`);
        $("#occupation_name").val(data.occupation_name);
        $('#id').val(data.id);

        // update button dynamic name
        $('#update-rto-btn').html(`Update ${data.occupation_name} Occuption Name`);
        

       

        $('#showdata').html(data);
    });

    setTimeout(() => {
        $('.alert-success').css('display', 'none');
    }, 2000);

</script>
@endpush
