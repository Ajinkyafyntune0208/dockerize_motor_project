@extends('layout.app', ['activePage' => 'policy-wording', 'titlePage' => __('Policy Wording')])

@section('content')
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Policy Wording</h5>
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif
              
              
               <div class="row mt-4 mb-2">
                <div class="col-12 d-flex justify-content-end stretch-card">
                    
                <a href="{{ route('admin.policy-wording.create') }}" class="btn btn-primary btn-sm">Create</a>
                    
                </div>
            </div>
                @if(!empty($files))
                <div class="table-responsive">
                    <table class="table table-striped" id="policy_wording_table">
                        <thead>
                            <tr>
                                <th scope="col">Company Name</th>
                                <th scope="col">Section</th>
                                <th scope="col">Bussiness Type</th>
                                <th scope="col">Policy Type</th>
                                <th scope="col" >Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($files as $key => $file)
                            @php
                            $filepath=str_ireplace("policy_wordings/","",$file);
                            $filename =explode('-',$filepath);
                            $removepdf=str_ireplace(".pdf","",$filepath);
                            $newfile =explode('-',$removepdf);
                            @endphp
                            <tr>
                                <td scope="col">{{ $newfile['0'] ?? '' }}</td>
                                <td scope="col">{{ $newfile['1'] ?? '' }}</td>
                                <td scope="col">{{ $newfile['2'] ?? '' }}</td>
                                <td scope="col">{{ $newfile['3'] ?? '' }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.policy-wording.destroy', rand()) }}" method="post">@csrf @method('DELETE')
                                    <input type="hidden" name="file"  value="{{ trim($newfile['0'].'-'.$newfile['1'].'-'.$newfile['2']) }}">
                                    <div class="btn-group">
                                        <a href="{{ file_url($file) }}" class="btn btn-sm btn-info" target="_blank"><i class="fa fa-eye"></i></a>
                                        
                                        <button type="button" class="btn btn-success btn-sm change-policy-wording" data="{{ ($filepath) }}" data-bs-toggle="modal" data-bs-target="#exampleModal"><i class="fa fa-edit"></i></button>
                                        
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you wants to delete Policy Wording PDF..?')"><i class="fa fa-trash"></i></button>
                                        
                                        <!--  -->
                                    </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </section>
</main>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Policy Wording - <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.policy-wording.store', rand()) }}" enctype="multipart/form-data" method="post"> @csrf 
                <div class="modal-body">
                    <div class="form-group">
                        <label class="btn btn-primary btn-sm mb-0"></i><input type="file" name="file" required accept="application/pdf"></label>
                        <input type="text" hidden name="file_name" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#policy_wording_table').DataTable({
            "initComplete" : function(){ //column wise filter
                var notApplyFilterOnColumn = [3];
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
							$('td.gtp-dt-select-filter-' + index).html('<input type="text" class="gtp-dt-input-filter form-control form-control-sm">');
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
    

        $(document).on('click', '.change-policy-wording', function () {
            $('#exampleModalLabel span').text($(this).attr('data'));
             $('input[name=file_name]').val($(this).attr('data'));
        });
        $('.selectpicker').selectpicker();

    });
</script>
@endpush