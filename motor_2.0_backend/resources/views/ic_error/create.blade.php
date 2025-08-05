@extends('layout.app', ['activePage' => 'IC_Error_Handler', 'titlePage' => __('Create new error msg')])

@section('content')
<style>
    @media (min-width: 576px){
        .modal-dialog {
            /* max-width: 911px; */
            margin: 34px auto;
            word-wrap: break-word;
        }
    }
</style>
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif


               <div class="row mt-4">
                <div class="col-12 d-flex justify-content-end stretch-card">

                </div>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-sm btn-primary"><i class="fa fa-arrow-circle-left"></i> Back</a><br>
            <div class="form-group">
                <form action="{{ route('admin.ic-error-handling.store') }}" method="post">@csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Company Name</label>
                                    <select name="company_alias" id="company_alias" data-style="btn-primary"
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        @foreach($companies as $company)
                                        <option {{ old('ic_alias') == ($company->company_alias ?? null) ? 'selected' : '' }} value="{{ $company->company_alias ?? '' }}">{{ $company->company_alias}}</option>
                                        @endforeach
                                    </select>
                                    @error('company_alias')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                            <label class="required">Section</label>
                                <select name="section" id="section" data-style="btn-primary"
                                    class="selectpicker w-100" data-live-search="true">
                                    <option value="" selected>Select Any One</option>
                                    <option value="car">CAR</option>
                                    <option value="bike">BIKE</option>
                                    <option value="cv">CV</option>
                                </select>
                                @error('section')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                            <label class="required">Type</label>
                                <select name="type" id="type" data-style="btn-primary"
                                    class="selectpicker w-100" data-live-search="true">
                                    <option value="" selected>Select Any One</option>
                                    <option value="quote">Quote</option>
                                    <option value="proposal">Proposal</option>
                                </select>
                                @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <Label class="required">IC Error</Label>
                                <textarea style="height: 10rem;" class = "form-control" name="ic_error" rows = "3" placeholder="Please Enter IC Error..." ></textarea>
                                @error('ic_error')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <Label class="required">Custom Error</Label>
                                <textarea style="height: 10rem;" class = "form-control" rows = "3" placeholder="Please Enter Custom Error..." name="custom_error" ></textarea>
                                @error('custom_error')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <Label class="required">Status</Label>
                                <select name="status" id="status" data-style="btn-primary" 
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        <option value="Y">Active</option>
                                        <option value="N">Inactive</option>
                                </select>
                                @error('status')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center h-100">
                                <input type="submit" value= "Submit" class="btn btn-outline-info btn-md w-100">
                            </div>
                        </div>
                    </div>
                    <!-- <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center h-100">
                                <input type="submit" class="btn btn-outline-info btn-md w-100">
                            </div>
                        </div>
                    </div> -->

                </form>
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
                <h5 class="modal-title" id="exampleModalLabel">Request / Response View <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">
                        <h2 >Status - <span id="showstatus"></span></h2>
                         <br>
                        <hr>
                        <h2 >Token</h2>
                        <span id="showtoken"></span> <br><br>
                        <h2 >Request</h2>
                        <hr>
                        <pre>
                            <span id="showdata"></span> <br><br>
                        </pre>
                        <hr>
                        <h2 >Response</h2>
                        <pre>
                            <span id="showresponse"></span>
                        </pre>
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
    $(document).ready(function() {

        $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });

        $('#push_data_api_table').DataTable({
            "initComplete" : function(){ //column wise filter
                var notApplyFilterOnColumn = [2];
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
							$('td.gtp-dt-select-filter-' + index).html('<input type="text" placeholder="Search Enquiry Id" class="gtp-dt-input-filter">');
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
    });
</script>
<script>
    $(document).on('click', '.view', function () {
        var data = $(this).attr('data');
        var token = $(this).attr('token');
        var response = $(this).attr('response');
        var status  = $(this).attr('status');
        var jsonObj = JSON.parse(data);
        var jsonPretty = JSON.stringify(jsonObj, null, '\t');

             $('#showdata').html(jsonPretty);
             $('#showtoken').html(token);
             $('#showresponse').html(response);
             $('#showstatus').html(status);
        });
</script>
@endpush
