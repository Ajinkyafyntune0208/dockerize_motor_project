@extends('admin_lte.layout.app', ['activePage' => 'vahan', 'titlePage' => __('Vahan Service configurator')])
@section('content')
<div class="card">
    <div class="card-body d-flex justify-content-center">
        @csrf @method('POST')
        <table style="width:70%" class="table table-bordered">
            <thead class="p-3 mb-2 bg-primary text-white">
                <tr>
                    <th class="text-center">Journey stage</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody class="text-center">
                <tr>
                    @foreach ($J_stages as $key => $J_stage)
                        <td scope="col">{{ $J_stage }}</td>
                        <td scope="col">
                            @can('vahan_configuration.edit')
                            <a class="btn btn-primary btn-sm"
                                href="{{ route('admin.vahan-service-stage-edit.stageEdit', ['key'=>$key, 'v_type'=>'2W']) }}"
                                title="2W"><i class="fa fa-motorcycle"
                                    aria-hidden="true"></i></a>
                            <a class="btn btn-primary btn-sm"
                                href="{{ route('admin.vahan-service-stage-edit.stageEdit', ['key'=>$key, 'v_type'=>'4W']) }}"
                                title="4W"><i class="fa fa-car"
                                    aria-hidden="true"></i></a>
                            <a class="btn btn-primary btn-sm"
                                href="{{ route('admin.vahan-service-stage-edit.stageEdit', ['key'=>$key, 'v_type'=>'CV']) }}"
                                title="CV"><i class="fa fa-truck"
                                    aria-hidden="true"></i></a>
                                    @endcan
                        </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
