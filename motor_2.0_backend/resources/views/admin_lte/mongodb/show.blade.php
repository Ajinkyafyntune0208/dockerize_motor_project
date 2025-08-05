@extends('admin_lte.layout.app', ['activePage' => 'Dashboard Mongo Logs', 'titlePage' => __('Dashboard Mongo Logs')])
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @php
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @endphp

        <pre>{{ $json }}</pre>
    </div>
</div>
@endsection
