@extends('layout.app', ['activePage' => 'abibl_data_migration', 'titlePage' => __('ABIBL Old Data Migration')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">ABIBL Old Data Migration
                            {{-- <a href="{{ route('admin.broker.create') }}" class="btn btn-primary float-end">Add Broker</i></a> --}}
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="" method="post" class="row" enctype="multipart/form-data">@csrf
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="active" for="label">Upload CSV File Only</label>
                                        <br>
                                        <input id="file" name="file" type="file" placeholder="file"
                                            value="{{ old('file') }}">{{-- accept=".xlsx" --}}
                                        @error('file')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-left">
                                        <button type="submit" class="btn btn-outline-primary"
                                            style="margin-top: 30px;">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <div class="table-responsive mt-5">
                        <table class="table table-striped border d-none">
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
            $('.table1').DataTable();
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
