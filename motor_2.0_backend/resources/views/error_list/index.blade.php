@extends('layout.app')
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Error List</h4>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.error-list-master.store') }}" method="POST"
                            enctype="multipart/form-data">
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
                        <a href="{{ route('admin.error-list-master.create') }}">Download Excel File Format</a>
                        <div class="table-responsive">
                            <table class="table table-striped" id="response_log">
                                <thead>
                                    <tr>
                                        <th scope="col">Sl.no</th>
                                        @foreach ($columnNames as $key => $columnName)
                                            <th scope="col">
                                                {{ str_replace('_', ' ', $columnName) }}
                                            </th>
                                        @endforeach
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($datas as $key => $data)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            @foreach ($columnNames as $columnName)
                                                <td>{{ $data->$columnName }}</td>
                                            @endforeach
                                            <td scope="col" class="text-right">
                                                <form action="{{ route('admin.error-list-master.destroy', $data) }}"
                                                    method="post" onsubmit="return confirm('Are you sure..?')"> @csrf
                                                    @method('DELETE')
                                                    <div class="btn-group">
                                                        <a href="{{ route('admin.error-list-master.show', $data) }}"
                                                            class="btn btn-sm btn-outline-info" title="view"><i
                                                                class="fa fa-eye"></i></a>
                                                        <a href="{{ route('admin.error-list-master.edit', $data) }}"
                                                            class="btn btn-sm btn-outline-success show-template"
                                                            title="Edit"><i class="fa fa-pencil-square-o"
                                                                aria-hidden="true"></i></a>
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i
                                                                class="fa fa-trash"></i></button>

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
    </div>
@endsection
