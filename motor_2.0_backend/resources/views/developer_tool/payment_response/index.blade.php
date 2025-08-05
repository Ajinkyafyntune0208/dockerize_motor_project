@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Payment Response Log')])
@section('content')

<style>

    #response_log_paginate{
        display: none;
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
                    <h5 class="card-title">Payment Response
                    </h5>
                    <a href="#" onclick="goBack()" class="btn btn-sm btn-primary"><i class="fa fa-arrow-circle-left"></i> Back</a><br><br>

                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm" id="response_log" width="100%">
                            <thead>
                                <tr>
                                    <th scope="col">Sr. No.</th>
                                    <th scope="col">Company Alias</th>
                                    <th scope="col">Section</th>
                                    <th scope="col">View Response</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payment as $key => $pr)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $pr->company_alias }}</td>
                                    <td>{{ $pr->section }}</td>
                                    <td><a href="#" class="btn btn-sm btn-info view" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal" data="{{ $pr->response }}"><i class="fa fa-eye"></i></a></td>
                                    <td>{{ $pr->created_at }}</td>
                                    <td>{{ $pr->updated_at }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $payment->links() }}
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
                <h5 class="modal-title" id="exampleModalLabel">Complete Response <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">
                        <h2>Response</h2>
                        <span id="showdata"></span>
                    </div>
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
    function goBack() {
    window.location.href = "/admin/payment-log";
}
    $(document).ready(function() {
        $('#response_log').DataTable({
            "scrollX": true,
            "scrollY": 400,
            "pageLength": 100,
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
							var select = $('<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true"><option value="">Select</option></select>')
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
    });

    $(document).on('click', '.view', function () {
        var data =$(this).attr('data');
        $('#showdata').html(data);
    });
    $(document).ready(function() {
        $('[name="response_log_length"]').attr({
            "data-style":"btn-sm btn-primary",
            "data-actions-box":"true",
            "class":"selectpicker px-3",
            "data-live-search":"true"
        });
        $('.selectpicker').selectpicker();
    })

</script>
@endpush
