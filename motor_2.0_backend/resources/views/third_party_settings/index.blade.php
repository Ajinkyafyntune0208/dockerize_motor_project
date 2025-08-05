@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Third Party Settings')])
@section('content')

<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Third Party Settings
                        <a href="{{ route('admin.third_party_settings.create') }}" class="view btn btn-primary float-end btn-sm">Add Third Party Setting</i></a>
                    </h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped" id="response_log">
                            <thead>
                                <tr>
                                    <th scope="col">Sr. No.</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Url</th>
                                    <th scope="col">Method</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($occuption as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $data->name }}</td>
                                    <td>{{ substr($data->url, 0,50) . '...' }}</td>
                                    <td>{{ $data->method }}</td>
                                    <td>
                                        <form action="{{ route('admin.third_party_settings.destroy',$data) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                            <div class="btn-group">
                                                <a href="{{ route('admin.third_party_settings.edit',$data->id) }}" class="btn btn-sm btn-success"><i class="fa fa-edit"></i></a>
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
    </div>
</div>
@endsection
