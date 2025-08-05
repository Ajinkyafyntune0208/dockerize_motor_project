@extends('layout.app', ['activePage' => 'Kafka Sync', 'titlePage' => __('Kafka Sync')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">

                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="card-body">
                        <h4 class="card-title">Kafka Sync</h4>
                        <p class="card-description"></p>

                        <form action="" method="GET">

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="list">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="col-md-5">
                            <div class="form-group">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="">From Date</label>
                                                <input type="date" name="from_date"
                                                    value="{{ old('from_date', request()->from_date ?? '') }}"
                                                    class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="">To Date</label>
                                                <input type="date" name="to_date"
                                                    value="{{ old('to_date', request()->to_date ?? '') }}"
                                                    class="form-control">
                                            </div>
                                            <input type="hidden" name="form_submit" value=true>
                                        </div>
                                    </div>

                                <button type="submit" class="btn btn-outline-primary">
                                    Search <i class="fa fa-search"></i>
                                </button>

                            </div>

                        </form>

                    </div>
                </div>
            </div>
            <div class="col-lg-12 grid-margin stretch-card">

                @if (!empty($logs))
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <h3 class="text-primary text-left mb-2 border-end">Fyntune Kafka Stats</h3>
                                    <ul>
                                        @foreach($paymentStatus as $Status)
                                        <li><b>{{ $Status->rb_status }} : </b> {{ $Status->total }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-primary text-left mb-2 border-start">RB Kafka Stats</h3>
                                    <ul>
                                        @if (in_array(gettype($logs),['array','object']))
                                            @foreach($logs as $key => $log)
                                                <li><b>{{ $key }} : </b> {{ $log }}</li>
                                            @endforeach
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <p style="font-size: 1rem;" class="card p-3 text-danger">
                        No Records or search for records
                    </p>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // $('#kafka-logs-table').DataTable();
    });
</script>
@endpush