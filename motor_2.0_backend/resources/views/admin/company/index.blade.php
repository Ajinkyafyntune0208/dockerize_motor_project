@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Company Logos</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <!-- <a href="{{ route('admin.email-sms-template.create') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a> -->
                </div>
            </div>
            @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
            @endif
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th scope="col">Sr. No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Logo</th>
                        <!-- <th scope="col">Status</th> -->
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companies as $key => $company)
                    <tr>
                        <th scope="col">{{ $key + 1 }}</th>
                        <td scope="col">{{ $company->company_name }}</td>
                        <td scope="col">{{ $company->company_alias }}</td>
                        <td scope="col">
                            <a href="{{ url('uploads/logos/'.$company->logo) }}" target="_blank">
                                <img src="{{ url('uploads/logos/'.$company->logo) }}" class="img-fluid" style="width: 70px;" alt="{{ $company->company_name }}">
                            </a>
                        </td>
                        <!-- <td scope="col"><a href="#" class="{{ $company->status == 'inactive' ? 'badge badge-danger' : 'badge badge-success' }} show-template" data="{{ route('admin.company.update', $company) }}" data-toggle="modal" data-target="#exampleModal">{{ $company->status }}</a></td> -->
                        <td scope="col" class="text-right">
                            <form action="{{ route('admin.company.destroy', $company) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                <div class="btn-group">
                                    <!-- <a href="{{ route('admin.company.show', $company) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                    <a href="#" data="{{ route('admin.company.update', $company) }}" class="btn btn-sm btn-outline-success show-template" data-toggle="modal" data-target="#exampleModal"><i class="fa fa-edit"></i></a>
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
</main>

<!--  -->
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="post" enctype="multipart/form-data" id="update-status">@csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Upload Logo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Upload Logo</label> <br>
                        <input type="file" name="logo" class="btn btn-primary">
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
<!--  -->
@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        $('.show-template').click(function() {
            console.log($(this).attr('data'));
            $('#update-status').attr('action', $(this).attr('data'));
            var html = '<option selected>' + $(this).text() + '</option>';
            $('#update-status select').prepend(html);
        });

        // $.ajax({
        //     url: 
        // })
    });
</script>
@endpush