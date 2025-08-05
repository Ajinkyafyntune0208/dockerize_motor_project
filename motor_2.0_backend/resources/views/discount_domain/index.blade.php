@extends('layout.app', ['activePage' => 'discount-domain', 'titlePage' => __('Disount Domain')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Disount Domain
                            <a href="{{ route('admin.discount-domain.create') }}" class="btn btn-primary float-end">Add
                                Disount Domain</a>
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
                                        <th scope="col">Label</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($discountDomains as $discountDomain)
                                        <tr>
                                            <td scope="row">{{ $loop->iteration }}</td>
                                            <td>{{ $discountDomain->domain }}</td>
                                            <td>
                                                <form
                                                    action="{{ route('admin.discount-domain.destroy', $discountDomain) }}"
                                                    method="post" onsubmit="return confirm('Are you sure..?')"> @csrf
                                                    @method('DELETE')
                                                    <div class="btn-group">
                                                        <a href="{{ route('admin.discount-domain.edit', $discountDomain->id) }}"
                                                            class="btn btn-sm btn-success"><i
                                                                class="fa fa-edit"></i></a>
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i
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
