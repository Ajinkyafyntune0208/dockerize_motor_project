@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Vahan Journey Configurator')])

@section('content')
<div class="container mt-5">
    <form action="{{ route('admin.vahan-journey-config.store') }}" method="POST">
        @csrf
        @method('POST')

        @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        <table class="table table-bordered" id="surepasstable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($blockerType as $type)
                <tr>
                    <td>{{ $type }}</td>
                    <td>
                      <input {{in_array($type, $pages) ? 'checked' : ''}} type="checkbox" name="{{ $type }}" id="">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </div>
    </form>
</div>
@endsection
