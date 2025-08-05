@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">USP</h5>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills nav-justified" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#car_usp" role="tab" aria-controls="car_usp" aria-selected="true">Car USP</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#bike_usp" role="tab" aria-controls="bike_usp" aria-selected="false">Bike USP</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#pcv_usp" role="tab" aria-controls="pcv_usp" aria-selected="false">PCV USP</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#gcv_usp" role="tab" aria-controls="gcv_usp" aria-selected="false">GCV USP</a>
                </li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="car_usp" role="tabpanel" aria-labelledby="car_usp-tab">
                    <div class="row mt-4">
                        <div class="col-12 text-right">
                            <a href="{{ route('admin.usp.create',['usp_type' => 'car']) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
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
                                <th scope="col">USP Description</th>
                                <th scope="col">IC Name</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($car_usps as $key => $car_usp)
                            <tr>
                                <th scope="col">{{ $key + 1 }}</th>
                                <td scope="col">{{ $car_usp->usp_desc }}</td>
                                <td scope="col">{{ $car_usp->ic_alias }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.usp.destroy', [$car_usp->car_usp_id, 'usp_type' => 'car' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            <!-- <a href="{{ route('admin.usp.show', [$car_usp->car_usp_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                            <a href="{{ route('admin.usp.edit', [$car_usp->car_usp_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="bike_usp" role="tabpanel" aria-labelledby="bike_usp-tab">
                    <div class="row mt-4">
                        <div class="col-12 text-right">
                            <a href="{{ route('admin.usp.create',['usp_type' => 'bike']) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
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
                                <th scope="col">USP Description</th>
                                <th scope="col">IC Name</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bike_usps as $key => $bike_usp)
                            <tr>
                                <th scope="col">{{ $key + 1 }}</th>
                                <td scope="col">{{ $bike_usp->usp_desc }}</td>
                                <td scope="col">{{ $bike_usp->ic_alias }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.usp.destroy', [ $bike_usp->bike_usp_id , 'usp_type' => 'bike' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            <!-- <a href="{{ route('admin.usp.show', [ $bike_usp->bike_usp_id , 'usp_type' => 'bike' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                            <a href="{{ route('admin.usp.edit', [ $bike_usp->bike_usp_id , 'usp_type' => 'bike' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="pcv_usp" role="tabpanel" aria-labelledby="pcv_usp-tab">
                    <div class="row mt-4">
                        <div class="col-12 text-right">
                            <a href="{{ route('admin.usp.create',['usp_type' => 'pcv']) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
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
                                <th scope="col">USP Description</th>
                                <th scope="col">IC Name</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pcv_usps as $key => $pcv_usp)
                            <tr>
                                <th scope="col">{{ $key + 1 }}</th>
                                <td scope="col">{{ $pcv_usp->usp_desc }}</td>
                                <td scope="col">{{ $pcv_usp->ic_alias }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.usp.destroy', [ $pcv_usp->pcv_usp_id , 'usp_type' => 'pcv' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            <!-- <a href="{{ route('admin.usp.show', [ $pcv_usp->pcv_usp_id , 'usp_type' => 'pcv' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                            <a href="{{ route('admin.usp.edit', [ $pcv_usp->pcv_usp_id , 'usp_type' => 'pcv' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="gcv_usp" role="tabpanel" aria-labelledby="gcv_usp-tab">
                    <div class="row mt-4">
                        <div class="col-12 text-right">
                            <a href="{{ route('admin.usp.create',['usp_type' => 'gcv']) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
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
                                <th scope="col">USP Description</th>
                                <th scope="col">IC Name</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gcv_usps as $key => $gcv_usp)
                            <tr>
                                <th scope="col">{{ $key + 1 }}</th>
                                <td scope="col">{{ $gcv_usp->usp_desc }}</td>
                                <td scope="col">{{ $gcv_usp->ic_alias }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.usp.destroy', [ $gcv_usp->gcv_usp_id , 'usp_type' => 'gcv' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                        <div class="btn-group">
                                            <!-- <a href="{{ route('admin.usp.show', [ $gcv_usp->gcv_usp_id , 'usp_type' => 'gcv' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                            <a href="{{ route('admin.usp.edit', [ $gcv_usp->gcv_usp_id , 'usp_type' => 'gcv' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a>
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
</main>

<!--  -->
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="post" id="update-status">@csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">Select</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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