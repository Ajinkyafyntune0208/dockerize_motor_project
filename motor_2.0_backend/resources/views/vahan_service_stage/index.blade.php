@extends('layout.app', ['activePage' => 'vahan', 'titlePage' => __('vahan')])
@section('content')
    <style>
        .table-bordered {
    border-collapse: collapse;
    margin: 25px 0;
    font-size: 0.9em;
    font-family: sans-serif;
    min-width: 400px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
}
.table-bordered thead tr {
    text-align: center;
}
.table-bordered th,
.table-bordered td {
    padding: 12px 15px;
}
        .table-bordered {
    word-wrap: break-word; 
    table-layout:fixed;
    width: 100%;
}
.table-bordered tbody tr.active-row {
    font-weight: bold;
    color: #009879;
}
        .table td {
            text-align: center;
        }

        .table {
            margin-left: auto;
            margin-right: auto;
        }
    </style>
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configuration</h5>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        @csrf @method('POST')
                        <table style="width:70%" class="table table-bordered">
                            <thead class="p-3 mb-2 bg-primary text-white">
                                <tr>
                                    <th class="text-center">Journey stage</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @foreach ($J_stages as $key => $J_stage)
                                        <td scope="col">{{ $J_stage }}</td>
                                        <td scope="col">
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
@endsection
