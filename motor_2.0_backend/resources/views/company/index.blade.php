@extends('layout.app', ['activePage' => 'company', 'titlePage' => __('Company Logo')])
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Company Logos</h5>
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
                                    <a href="{{ url('uploads/logos/'.trim($company->company_alias).'.png') }}" target="_blank">
                                        <img src="{{ url('uploads/logos/'.trim($company->company_alias).'.png') }}" class="img-fluid" style="width: 70px; border-radius: 0px" alt="{{ $company->company_name }}">
                                    </a>
                                </td>
                                <!-- <td scope="col"><a href="#" class="{{ $company->status == 'inactive' ? 'badge badge-danger' : 'badge badge-success' }} show-template" data="{{ route('admin.company.update', $company) }}" data-toggle="modal" data-target="#exampleModal">{{ $company->status }}</a></td> -->
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.company.destroy', $company) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            @can('company.show')
                                            <a href="{{ url('uploads/logos/'.trim($company->company_alias).'.png') }}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a>
                                            @endcan
                                            @can('company.edit')
                                            <a href="#" data="{{ route('admin.company.update', $company) }}" class="btn btn-sm btn-outline-success show-template" data-bs-toggle="modal" data-bs-target="#exampleModal"><i class="fa fa-edit"></i></a>
                                            @endcan
                                            @can('company.delete')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                            @endcan
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

<!--  -->
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="post" enctype="multipart/form-data" id="update-status">@csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Upload Logo</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="required">Upload Logo</label> <br>
                        <input  required type="file" name="logo" class="btn btn-primary">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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