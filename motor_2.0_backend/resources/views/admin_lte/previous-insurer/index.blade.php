@extends('admin_lte.layout.app', ['activePage' => 'previous-insurer', 'titlePage' => __('Previous Insurer Logo')])
@section('content')
<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Previous Insurer</th>
                    <th>Company Alias</th>
                    <th>Logo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($previousinsurer as $key => $value)
                <tr>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('previous_insurer_logos.edit')
                            <a class="btn btn-info mr-1 show-template" href="#" data-toggle="modal" data-target="#modal-default" data-logo-path="previous_insurer_logos" data-logo-name="{{ Str::camel($value->company_alias) . '.png'}}"><i class="far fa-edit"></i></a>
                            @endcan
                        </div>
                    </td>
                    <td>{{$value->previous_insurer}}</td>
                    <td>{{$value->company_alias}}</td>
                    <td>
                        <a href="{{ $value->logo }}" target="_blank">
                            <img src="{{ $value->logo }}" class="img-fluid" style="width: 70px;" alt="{{ $value->company_name }}">
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('admin.previous-insurer.store') }}" method="post" enctype="multipart/form-data" id="update-status">@csrf @method('POST')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalCenterTitle">Upload Logosss</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Upload Logo</label> <br>
                    <input type="file" name="image" class="btn btn-primary">
                    <input type="hidden" name="path" id="path">
                    <input type="hidden" name="name" id="name">
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
    $(document).ready(function() {
        $('.show-template').click(function() {
            console.log($(this).attr('data'));
            $('#name').val($(this).attr('data-logo-name'));
            $('#path').val($(this).attr('data-logo-path'));
            $('#update-status').attr('action', $(this).attr('data'));
            var html = '<option selected>' + $(this).text() + '</option>';
            $('#update-status select').prepend(html);
        });

        // $.ajax({
        //     url:
        // })
    });
</script>
@endsection('scripts')
