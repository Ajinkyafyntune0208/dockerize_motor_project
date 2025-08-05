@extends('admin_lte.layout.app', ['activePage' => 'manufacturer', 'titlePage' => __('Manufacturer')])
@section('content')
<style>
    :root{
    --primary: #1f3bb3;
    }
    .btn-cus{
        width: 100%;
        height: 40px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);

        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
    tr {
        height: 50px;
    }
</style>
@if (session()->has('message'))
<div class="alert alert-danger">
{{ session('message') }}
</div>
@endif
<div class="content">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <!-- <h5 class="card-title">Manufacturer</h5> -->
                    <!-- <ul class="nav nav-pills nav-justified" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{(in_array($type, ['gcv_manufacturer', '']) ? 'active' : '') }}" id="home-tab"  href="{{route('admin.manufacturer.index')}}?type=gcv_manufacturer"  aria-controls="gcv_manufacturer" aria-selected="true" >GCV Manufacturer</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{$type == 'pcv_manufacturer' ? 'active' : '' }}" id="profile-tab"  href="{{route('admin.manufacturer.index')}}?type=pcv_manufacturer" aria-controls="pcv_manufacturer" aria-selected="false">PCV Manufacturer</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{$type == 'motor_manufacturer' ? 'active' : '' }}" id="profile-tab"  href="{{route('admin.manufacturer.index')}}?type=motor_manufacturer" aria-controls="motor_manufacturer" aria-selected="false">Motor Manufacturer</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{$type == 'bike_manufacturer' ? 'active' : '' }}" id="profile-tab"  href="{{route('admin.manufacturer.index')}}?type=bike_manufacturer" aria-controls="bike_manufacturer" aria-selected="false">Bike Manufacturer</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{$type == 'misc_manufacturer' ? 'active' : '' }}" id="profile-tab"  href="{{route('admin.manufacturer.index')}}?type=misc_manufacturer" aria-controls="bike_manufacturer" aria-selected="false">Misc Manufacturer</a>
                        </li>
                    </ul> -->

                    <div class="row mb-3 mt-4">
                        <div class="col-12 col-sm-2">
                                <a href="{{route('admin.manufacturer.index')}}?type=gcv_manufacturer" class="btn btn-cus {{(in_array($type, ['gcv_manufacturer', '']) ? 'active' : '') }}">GCV Manufacturer</a>
                        </div>
                        <div class="col-12 col-sm-2">
                                <a href="{{route('admin.manufacturer.index')}}?type=pcv_manufacturer" class="btn btn-cus {{$type == 'pcv_manufacturer' ? 'active' : '' }}">PCV Manufacturer</a>
                        </div>
                        <div class="col-12 col-sm-3">
                                <a href="{{route('admin.manufacturer.index')}}?type=motor_manufacturer" class="btn btn-cus {{$type == 'motor_manufacturer' ? 'active' : '' }}">Motor Manufacturer</a>
                        </div>
                        <div class="col-12 col-sm-3">
                                <a href="{{route('admin.manufacturer.index')}}?type=bike_manufacturer" class="btn btn-cus {{$type == 'bike_manufacturer' ? 'active' : '' }}">Bike Manufacturer</a>
                        </div>
                        <div class="col-12 col-sm-2">
                                <a href="{{route('admin.manufacturer.index')}}?type=misc_manufacturer" class="btn btn-cus {{$type == 'misc_manufacturer' ? 'active' : '' }}">Misc Manufacturer</a>
                        </div>

                    </div>

                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="gcv_manufacturer" role="tabpanel" aria-labelledby="gcv_manufacturer-tab">
                            <div class="row mt-4">
                                <div class="col-12 d-flex justify-content-end stretch-card">
                                </div>
                            </div>
                            <div class="table-responsive mt-2">
                                <table class="table table-striped table-bordered" id="mmv_table">
                                    @foreach($manufacturer as $key => $row)
                                    @if ($loop->first)
                                    <thead>
                                        <tr>
                                             <td></td>
                                             <td>Logo</td>
                                            @foreach($row as $key => $dd)
                                            @php
                                                if( $key =='image_url'){
                                                    continue;
                                                }
                                            @endphp
                                            <th>
                                                {{ $key }}
                                            </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @endif
                                        <tr>
                                            <td scope="col">
                                            @php
                                               switch ($type) {
                                                case 'pcv_manufacturer':
                                                    $typ = 'pcv';
                                                    break;
                                                case 'gcv_manufacturer':
                                                    $typ = 'gcv';
                                                    break;
                                                case 'motor_manufacturer':
                                                    $typ = 'car';
                                                    break;
                                                case 'bike_manufacturer':
                                                    $typ = 'bike';
                                                    break;
                                                case 'misc_manufacturer':
                                                    $typ = 'misc';
                                                    break;
                                                default:
                                                    $typ = 'gcv';
                                                    break;
                                               }
                                            @endphp

                                                <form action="{{-- route('admin.usp.destroy', [ $manufacturer->gcv_id, 'usp_type' => 'car' ]) --}}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                                    <div class="btn-group">
                                                        @can('manufacturer.edit')
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn btn-success upload-logo-modal"  data-logo-path ="uploads/vehicleModels/{{$typ}}" data-logo-name="{{ Str::lower(implode('_', explode(' ', $row->manf_name))). '.png' }}"><i class="fa fa-edit"></i></a>
                                                       @endcan
                                                    </div>
                                                </form>
                                            </td>
                                            <td scope="col"><img src="{{$row->image_url}}" style="width:70px;" class="img-fluid" loading="lazy">
                                            </td>
                                            @foreach($row as $key => $dd)
                                            @php
                                                if( $key =='image_url'){
                                                    continue;
                                                }
                                            @endphp
                                            <td>
                                                {{ $dd }}
                                            </td>
                                            @endforeach
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {{-- <div class="tab-pane fade" id="tractor_manufacturer" role="tabpanel" aria-labelledby="tractor_manufacturer-tab">
                            <div class="row mt-4">
                                <div class="col-12 d-flex justify-content-end stretch-card">

                                </div>
                            </div>
                            <div class="table-responsive">
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
                                        @foreach($tractor_manufacturer as $key => $manufacturer)
                                        <tr>
                                            <th scope="col">{{ $key + 1 }}</th>
                                            <td scope="col">{{ $bike_usp->usp_desc }}</td>
                                            <td scope="col">{{ $bike_usp->ic_alias }}</td>
                                            <td scope="col" class="text-right">
                                                <form action="{{ route('admin.usp.destroy', [ $bike_usp->bike_usp_id , 'usp_type' => 'bike' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                                    <div class="btn-group">
                                                        <!-- <a href="{{ route('admin.usp.show', [ $bike_usp->bike_usp_id , 'usp_type' => 'bike' ]) }}" class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a> -->
                                                            @can('manufacturer.edit')
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn btn-success upload-logo-modal" data-logo-name="{{ Str::lower(implode('_', explode(' ', $manufacturer->manf_name))). '.png' }}"><i class="fa fa-edit"></i></a>
                                                        @endcan
                                                        @can('usp.delete')
                                                        <!-- <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button> -->
                                                        @endcan
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div> --}}
                    </div>
                </div>
            </div>

            <!--  -->
            <!-- Modal -->
            <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form action="{{ route('admin.manufacturer.store') }}" enctype="multipart/form-data" method="post" id="update-logo">@csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLabel">Status</h5>
                                <!-- <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button> -->
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="image" class="required" style="margin-left: 40px">Logo</label>
                                    <br>
                                    <input type="file" class="btn btn-primary" name="image" accept=".png" style="margin-left: 40px" required>
                                    <input type="hidden" name="name" id="name">
                                    <input type="hidden" name="path" id="path">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> -->
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!--  -->
@endsection('content')
@section('scripts')
<script>
    $(document).ready(function() {
        $('.table').DataTable();
        $(document).on('click', '.upload-logo-modal', function() {
            $('#name').val($(this).attr('data-logo-name'));
            $('#path').val($(this).attr('data-logo-path'));
        });
    });
</script>
@endsection('scripts')