@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('RTO Master')])
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
                    <h5 class="card-title">RTO Master
                        <a href="/admin/sync_rto" class="view btn btn-primary float-end btn-sm" >Sync RTO</a>
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
                                    <th scope="col">RTO Code</th>
                                    <th scope="col">RTO Name</th>
                                    <th scope="col">State</th>
                                    <th scope="col">Zone</th>
                                    <th scope="col">status</th>
                                    <th scope="col">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rto_master as $key => $rto_data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $rto_data->rto_code }}</td>
                                    <td>{{ $rto_data->rto_name }}</td>
                                    <td>{{ $rto_data->state_name }}</td>
                                    <td>{{ $rto_data->zone_name }}</td>
                                    @if ($rto_data->status !== 'Active')
                                        <td class="text-danger">{{ $rto_data->status }}</td>
                                        @else
                                        <td class="text-success">{{ $rto_data->status }}</td>
                                    @endif
                                    <td><a href="#" class="view text-dark" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal" data="{{ $rto_data }}"><i style="font-size: 1.2rem;" class="mdi mdi-grease-pencil"></i></a></td>
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
                <h5 class="modal-title" id="exampleModalLabel"> Edit RTO <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">

                        <form action="{{ route('admin.rto-master.update', [1, 'data' => request()->all()]) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <input type="text" name="id" id="id" hidden />
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_code">RTO Code</label>
                                        <input type="text" class="form-control" name="rto_code" id="rto_code" required/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_name">RTO Name</label>
                                        <input type="text" class="form-control" name="rto_name" id="rto_name" required/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_state">State</label>
                                        <select class="form-control" name="rto_state" id="rto_state" required>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                                    <div class="form-group">
                                        <label for="rto_zone">Zone</label>
                                        <select class="form-control" name="rto_zone" id="rto_zone" required>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                                    <div class="form-group">
                                        <label for="rto_status">Status</label>
                                        <select class="form-control" name="rto_status" id="rto_status" required>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                                    <button type="submit" id="update-rto-btn" class="btn btn-primary w-100">Update RTO</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>

        </div>
    </div>
</div>

{{-- Upload RTO Modal --}}
{{-- <div class="modal fade" id="rtoModal" tabindex="-1" aria-labelledby="rtoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rtoModalLabel"> Upload RTO <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <input type="file" name="rto_master_file" required/>
                            </div>
                        </div>

                        <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <button class="btn btn-primary">Upload</button>
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
</div> --}}

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
							var select = $('<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker" data-live-search="true"><option value="">Select</option></select>')
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

        $('#exampleModalLabel').html(`Edit ${data.rto_name} RTO Details:`);

        $("#rto_code").val(data.rto_code);
        $("#rto_name").val(data.rto_name);
        $("#rto_state").val(data.state_id);
        $("#rto_zone").val(data.zone_id);
        $("#rto_status").val(data.status);

        // update button dynamic name
        $('#update-rto-btn').html(`Update ${data.rto_name} RTO`);

        $('#id').val(data.rto_id);

        $('#showdata').html(data);
    });
    $(document).ready(function() {
        $('[name="response_log_length"]').attr({
            "data-style":"btn-sm btn-primary",
            "data-actions-box":"true",
            "class":"selectpicker w-100 px-3",
            "data-live-search":"true"
        });
        $('.selectpicker').selectpicker();
    })

    setTimeout(() => {
        $('.alert-success').css('display', 'none');
    }, 2000);

</script>
@endpush
