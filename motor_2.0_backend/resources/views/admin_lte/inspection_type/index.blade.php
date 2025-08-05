@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Inspection Type')])

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            @can('inspection_type.create')
            <a href="{{ route('admin.inspection.create') }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add
                </button>
            </a>
            @endcan
        </div>
    </div>

    <div class="card-body">
        <table id="data-table" class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th scope="col">Sr. No.</th>
                    <th scope="col">Company Name</th>
                    <th scope="col">Manual Inspection</th>
                    <th scope="col">Self Inspection</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $key => $datas)
                <tr>
                    <th scope="col">{{ $key + 1 }}</th>
                    <td scope="col">{{ $datas->company_name }}</td>
                    <td scope="col">{{ $datas->Manual_inspection }}</td>
                    <td scope="col"> {{$datas->Self_inspection}} </td>
                    <td>
                        <div class="btn-group btn-group-toggle">

                            <!-- <a class="btn btn-info mr-1" href="#" title="edit"><i class="far fa-edit"></i></a> -->
                            @can('inspection_type.edit')
                            <a class="btn btn-info mr-1" href="{{url('admin/inspection/edit/'.base64_encode($datas->id))}}" title="edit"><i class="far fa-edit"></i></a>
                            @endcan

                        </div>
                    </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection('content')