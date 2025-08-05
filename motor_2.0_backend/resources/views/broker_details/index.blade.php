@extends('layout.app', ['activePage' => 'broker_details', 'titlePage' => __('broker_details')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Broker Details
                            <a href="{{ route('admin.broker.create') }}" class="btn btn-primary float-end">Add Broker</i></a>
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="table-responsive mt-5">
                            <table class="table table-striped border">
                                <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Frontend Url</th>
                                    <th scope="col">Backand Url</th>
                                    <th scope="col">Environment</th>
                                    <th scope="col">Support Email</th>
                                    <th scope="col">Support Tollfree</th>
                                    <th scope="col">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($broker_details as $broker)
                                    <tr>
                                        <td scope="row">{{ $loop->iteration }}</td>
                                        <td>{{ $broker->name }}</td>
                                        <td>{{ $broker->frontend_url }} <i role="button" class="text-info ml-2 fa fa-copy"></i></td>
                                        <td>{{ $broker->backend_url }} <i role="button" class="text-info ml-2 fa fa-copy"></i></td>
                                        <td>{{ $broker->environment }}</td>
                                        <td>{{ $broker->support_email }}</td>
                                        <td>{{ $broker->support_tollfree }}</td>
                                        <td>
                                            <form action="{{ route('admin.broker.destroy', $broker) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.broker.edit', $broker->id) }}" class="btn btn-sm btn-success"><i class="fa fa-edit"></i></a>
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
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.table').DataTable();
            $(document).on('click', '.fa.fa-copy', function() {
                // $('.fa.fa-copy').click(function() {
                var text = $(this).parent('td').text();
                const elem = document.createElement('textarea');
                elem.value = text;
                document.body.appendChild(elem);
                elem.select();
                document.execCommand('copy');
                document.body.removeChild(elem);
                alert('Text copied...!')
            });
        });
    </script>
@endpush
