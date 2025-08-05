@extends('admin.layout.app')
@section('content')
<main>
    <section class="mb-4">
        @if (!empty($configs))
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped border">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Label</th>
                                <th scope="col">Key</th>
                                <th scope="col">Value</th>
                                <th scope="col">Environment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($configs as $config)
                            <tr>
                                <td scope="row">{{ $loop->iteration }}</td>
                                <td>{{ $config->label }}</td>
                                <td>{{ $config->key }} <i role="button" class="text-info ml-2 fa fa-copy"></i></td>
                                <td>{{ $config->value }} <i role="button" class="text-info ml-2 fa fa-copy"></i></td>
                                <td>{{ $config->environment }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <p>No Records or search for records</p>
        @endif
    </section>
</main>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        $('.table').DataTable();

        $('.fa.fa-copy').click(function() {
            var text = $(this).parent('td').text();
            const elem = document.createElement('textarea');
            elem.value = text;
            document.body.appendChild(elem);
            elem.select();
            document.execCommand('copy');
            document.body.removeChild(elem);
        });
        // e.preventDefault();
        // 	$(this).closest('.form-group').find('input').select();
        // 	document.execCommand('copy') ? alert('Text Copied..!') : alert('Something went wrong..!');
    });
</script>
@endpush