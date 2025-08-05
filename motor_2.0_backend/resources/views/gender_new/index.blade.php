@extends('layout.app')
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Gender Mapping</h4>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <form action="{{ route('admin.gender-master.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active required" for="excelFile">Choose excel file</label>
                                    <input type="file" name="excelfile" required>
                                </div>

                            </div>
                            <div class="col-sm-3 text-right">
                                <div class="form-group">
                                    <button class="btn btn-outline-primary" type="submit" style="margin-top: 30px;"><i
                                            class="fa fa-submit"></i> Submit</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <a href="{{route('admin.gender-master.create')}}">Download Excel File Format</a>
                    <div class="table-responsive">
                        <table class="table table-striped" id="response_log">
                            <thead>
                                <tr>
                                    <th scope="col">Sl.no</th>
                                    @foreach($columnNames as $key => $columnName)
                                    <th scope="col">{{ $columnName }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($datas as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    @foreach($columnNames as $columnName)
                                    <td>{{ $data->$columnName }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
