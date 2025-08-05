@extends('admin_lte.layout.app', ['activePage' => 'policy-wording', 'titlePage' => __('Policy Wording')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            @can('policy_wording.create')
            <a href="{{ route('admin.policy-wording.create') }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add
                </button>
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Company Name</th>
                    <th>Section</th>
                    <th>Bussiness Type</th>
                    <th>Policy Type</th>
                </tr>
            </thead>
            <tbody>
                    @foreach($files as $key => $file)
                    @php
                    $filepath=str_ireplace("policy_wordings/","",$file);
                    $filename =explode('-',$filepath);
                    $removepdf=str_ireplace(".pdf","",$filepath);
                    $newfile =explode('-',$removepdf);

                    $newfilepart[0] = ((isset($newfile[0])) ? $newfile[0] ?? '' : '');
                    $newfilepart[1] = ((isset($newfile[1])) ? $newfile[1] ?? '' : '');
                    $newfilepart[2] = ((isset($newfile[2])) ? $newfile[2] ?? '' : '');
                    $newfilepart[3] = ((isset($newfile[3])) ? $newfile[3] ?? '' : '');
                    @endphp
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            <form action="{{ route('admin.policy-wording.destroy', rand()) }}" method="post">@csrf @method('DELETE')
                                <input type="hidden" name="file"  value="{{ trim($newfilepart['0'].'-'.$newfilepart['1'].'-'.$newfilepart['2']) }}">
                                <div class="btn-group">
                                    <a href="{{ file_url($file) }}" class="btn btn-sm btn-info mx-1" target="_blank" rel="noopener noreferrer"><i class="fa fa-eye"></i></a>

                                    <a class="btn btn-success mr-1 change-policy-wording mx-1" href="#" data-toggle="modal" data-target="#modal-default" data="{{ $filepath }}"><i class="far fa-edit"></i></a>


                                    <button type="submit" class="btn btn-danger btn-sm mx-1" onclick="return confirm('Are you wants to delete Policy Wording PDF..?')"><i class="fa fa-trash"></i></button>

                                    <!--  -->
                                </div>
                            </form>
                        </div>
                    </td>
                    <td>{{ $newfilepart[0] }}</td>
                    <td>{{ $newfilepart[1] }}</td>
                    <td>{{ $newfilepart[2] }}</td>
                    <td>{{ $newfilepart[3] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('admin.policy-wording.store', rand()) }}" method="post" id="update-status">@csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Edit Policy Wording - <span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                <input type="text" name="id" id="id"  hidden/>
                    <label class="btn btn-primary btn-sm mb-0"></i><input type="file" name="file" required accept="application/pdf"></label>
                    <input type="text" hidden name="file_name" value="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
  </div>
</div>
@endsection('content')
@section('scripts')
<script>

    $(function () {
    $("#data-table").DataTable({
      "responsive": false, "lengthChange": true, "autoWidth": false,
       scrollX: true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(2))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });


  $(document).on('click', '.change-policy-wording', function () {
            $('#exampleModalLabel span').text($(this).attr('data'));
             $('input[name=file_name]').val($(this).attr('data'));
        });

</script>
@endsection('scripts')
