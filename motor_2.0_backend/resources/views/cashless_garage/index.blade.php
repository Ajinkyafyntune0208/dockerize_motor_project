@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Cashless Garage')])
@section('content')

<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Cashless Garage
                        <br/><br/>
                        <a href="{{ route('admin.cashless_garage.create',['file'=>'csv_file']) }}" class="btn btn-sm btn-primary justify-content-end"><i class="fa fa-plus-circle"></i> Upload CSV</a>
                    </h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
