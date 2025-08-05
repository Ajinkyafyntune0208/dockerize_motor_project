@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css" />

<div class="content-wrapper">
    <div class="grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-9" style="margin-top: 14px;">
                        <h4 class="card-title">Finsall Configuration</h4>
                    </div>
                    <div class="col-sm-3">
                        {{-- <a class="btn btn-primary" href="{{ route('admin.finsall-configuration.create') }}">+ Add CKYC Failure message</i></a> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                @php
                $dropDownValues = [10,20,30,40,50];
                @endphp
                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table-striped table border">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Company</th>
                                    <th scope="col">Section</th>
                                    <th scope="col">Premium Type</th>
                                    <th scope="col">Payment Mode</th>
                                    <th scope="col">Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $key => $row)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>
                                        --
                                        {{-- <div class="btn-group">
                                            <a class="btn btn-warning p-0 btn-sm mr-1" style="padding-right: 6px; padding-left: 10px;" href="{{route('admin.finsall-configuration.edit', http_build_query($row))}}">
                                                <i class="fa fa fa-edit p-1"></i>
                                            </a>
                                            <a class="btn btn-danger p-0 btn-sm mr-1 statusupdate" style="padding-right: 6px; padding-left: 10px;" href="{{route('admin.finsall-configuration.destroy', http_build_query($row))}}">
                                                <i class="fa fa fa-trash p-1"></i>
                                            </a>
                                        </div> --}}
                                    </td>
                                    <td>{{$row['company']}}</td>
                                    <td>{{$row['section']}}</td>
                                    <td>{{$row['premium_type']}}</td>
                                    <td>{{$row['mode']}}</td>
                                    <td>{{$row['active']}}</td>
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
@push('scripts')
<script type="text/javascript" src="{{ asset('js/buttons.dataTables.buttons.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/buttons.jszip.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/buttons.html5.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/buttons.print.min.js') }}"></script>

<script>
    $(document).ready(function() {
        $('.table').DataTable({
            paging: false,
            ordering: false,
            info: false,
            searching: false,
            // dom: 'Bfrtip',
            // dom: 'lPfBrtpi',
            // buttons: ['csv', 'excel'],
        });
    });
</script>
@endpush
