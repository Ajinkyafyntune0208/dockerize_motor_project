@extends('admin_lte.layout.app', ['activePage' => 'vahan_upload', 'titlePage' => __('vahan upload')])
@section('content')
<div class="card">
    <div class="card-body">
        @if (session('success'))
        <div id="successMessage" class="alert alert-success text-center">
            {{ session('success') }}
        </div>
        @endif

        @if (session('error'))
        <div id="failureMessage" class="alert alert-danger text-center">
            {{ session('error') }}
        </div>
        @endif
        <form action="{{ route('admin.vahan-upload.store') }}" method="POST" id="form" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="form-group w-100">
                    <label for="excelFile">Choose json file</label>
                    <input type="file" name="jsonfile">
                    @error('jsonfile')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-primary w-100" style="margin-top:16%">
                </div>
            </div>
        </form>
        <button type="button" class="btn btn-secondary mt-3" data-toggle="modal" data-target="#excelDownloadModal">
            Excel Download
        </button>

        <div class="modal fade" id="excelDownloadModal" tabindex="-1" role="dialog" aria-labelledby="excelDownloadModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="" method="GET" id="downloadForm">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="excelDownloadModalLabel">Select Date Range</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="start_date">From Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">To Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Download Excel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            @if (!empty($logs))
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>#</th>
                        <th>file name</th>
                        <th>Total Count</th>
                        <th>Processed Count</th>
                        <th>Exisiting Count</th>
                        <th>Status</th>
                        <th>created at</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $key => $value)
                    <tr>
                        <td>
                            <form action="{{ route('admin.vahan-upload.destroy', $value->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                @if($value->status == 0)
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?');">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @else
                                <button disabled type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?');">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                            </form>
                        </td>
                        <td>{{$key + 1 }}</td>
                        <td>{{$value->file_name}}</td>
                        <td>{{$value->total_count}}</td>
                        <td>{{$value->processed_count}}</td>
                        <td>{{$value->exisiting_count}}</td>
                        <td>
                            @if($value->status == 0)
                            Pending
                            @elseif($value->status == 1)
                            Processing
                            @else
                            Success
                            @endif
                        </td>
                        <td>
                            {{$value->created_at}}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div
            </div>
    </div>
    @endsection
    @section('scripts')
    <script src="{{asset('admin1/js/vahan-upload/vahanUpload.js')}}"></script>
    @endsection