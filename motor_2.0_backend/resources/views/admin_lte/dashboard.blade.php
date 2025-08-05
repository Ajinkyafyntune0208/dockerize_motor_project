@extends('admin_lte.layout.app', ['activePage' => 'dashboard', 'titlePage' => __('Dashboard')])
@section('content')
{{-- <div class="col-md-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Links</h3>
        </div>
        <div class="card-body">
        @can('log.list')
            <button type="button" class="btn btn-outline-primary btn-block" data-url="{{ route('admin.log.index') }}">Logs</button>
        @endcan
        @can('log.list')
            <button type="button" class="btn btn-outline-info btn-block" data-url="{{ route('admin.ckyc-logs.index') }}">Ckyc Logs</button>
        @endcan 
        @can('company.list')
            <button type="button" class="btn btn-outline-danger btn-block" data-url="{{ route('admin.journey-data.index') }}">Journey Data</button>
        @endcan
        @can('log.list')
            <button type="button" class="btn btn-outline-warning btn-block" data-url="{{ route('admin.trace-journey-id.index') }}">Get Trace ID</button>
        @endcan
        @can('configuration.list') 
            <button type="button" class="btn btn-outline-success btn-block" data-url="{{ route('admin.configuration.index') }}">System Configuration</button>
        @endcan 
        @if (auth()->user()->can('report.list')) 
            <button type="button" class="btn btn-outline-secondary btn-block" data-url="{{ route('admin.report.index') }}">Policy Reports</button>
        @endif
        @can('master_policy.list') 
            <button type="button" class="btn btn-outline-dark btn-block" data-url="{{ route('admin.master-product.index') }}">Master Product</button>
        @endcan
        <!--@can('log.list') 
            <button type="button" class="btn btn-outline-warning btn-block" data-url="{{ route('admin.kafka-logs.index') }}">Kafka Logs</button>
        @endcan -->
        @if (auth()->user()->can('report.list')) 
            <button type="button" class="btn btn-outline-primary btn-block" data-url="{{ route('admin.rc-report.index') }}">Rc Reports</button>
        @endif 
        @can('log.renewal-api') 
            <button type="button" class="btn btn-outline-info btn-block" data-url="{{ route('admin.renewal-data-logs.index') }}">Renewal Data Api Logs</button>
        @endcan
        @can('log.list') 
            <button type="button" class="btn btn-outline-danger btn-block" data-url="{{ route('admin.encrypt-decrypt') }}">Encryption/Decryption</button>
        @endcan 
            <button type="button" class="btn btn-outline-warning btn-block" data-url="{{ route('admin.ic-config.credential.index') }}">IC Configurator</button>
        </div>
    </div>
    <!-- /.card -->
</div> --}}

<style>
    .btn-cus-v3{
        text-decoration: none;
        color: #2e2e2e;
    }
    .btn-cus-v3:hover{
        background: linear-gradient(to right, #008cff 0%, #0059ff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: inline-block;
    }
    .ulink-cus-v1 {
        counter-reset: index;  
        padding: 0;
        max-width: 300px;
    }

    /* List element */
    .link-cus-v1{
        counter-increment: index; 
        display: flex;
        align-items: center;
        /* padding: 12px 0; */
        box-sizing: border-box;
    }


    /* Element counter */
    .link-cus-v1::before {
        content: counters(index, ".", decimal-leading-zero);
        font-size: 1.5rem;
        text-align: right;
        font-weight: bold;
        min-width: 50px;
        padding-right: 12px;
        font-variant-numeric: tabular-nums;
        align-self: flex-start;
        background-image: linear-gradient(to bottom, aquamarine, blue);
        background-attachment: fixed;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .link-cus-v1 + .link-cus-v1 {
        border-top: 1px solid rgba(14, 14, 14, 0.2);
    }
    .grey-bg {  
    background-color: #F5F7FA;
}

</style>
    <div class="row flex-grow">
        <div class="col-md-8 col-lg-4 grid-margin stretch-card" style="margin-left: 30px">
            <div class="card card-rounded shadow" style="border-radius:50px; border-radius: 15px">
                <div class="card-body card-rounded">
                    @if (!$customQuickLink)
                    <h5>Quick Links</h5>
                    <ul class="ulink-cus-v1">
                        @can('log.list')
                            <li class="link-cus-v1">
                                <a class="btn-cus-v3" href="{{ route('admin.log.index') }}">Logs</a>
                            </li>
                        @endcan

                        @can('ckyc_logs.list')
                            <li class="link-cus-v1">
                                <a class="btn-cus-v3" href="{{ route('admin.ckyc-logs.index') }}">Ckyc Logs</a>
                            </li>
                        @endcan 

                        @can('journey_data.show')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.journey-data.index') }}">Journey Data</a>
                        </li>
                        @endcan

                        @can('get_trace_id.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.trace-journey-id.index') }}">Get Trace ID</a>
                        </li>
                        @endcan

                        @can('system_configuration.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.configuration.index') }}">System Configuration</a>
                        </li>
                        @endcan

                        @if (auth()->user()->can('policy_report.list'))
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.report.index') }}">Policy Reports</a>
                        </li>
                        @endif

                        @can('master_product.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.master-product.index') }}">Master Product</a>
                        </li>
                        @endcan

                        @can('vahan_service.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.vahan_service_dash.dash') }}">Vahan Service</a>
                        </li>
                        @endcan
                        
                        @can('kafka_logs.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.kafka-logs.index') }}">Kafka Logs</a>
                        </li>
                        @endcan

                        @if (auth()->user()->can('rc_report.list'))
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.rc-report.index') }}">RC Reports</a>
                        </li>
                        @endif

                        @can('renewal_data_api_logs.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.renewal-data-logs.index') }}">Renewal Data Api Logs</a>
                        </li>
                        @endcan

                        @can('encryption_decryption.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.encrypt-decrypt') }}">Encryption/Decryption</a>
                        </li>
                        @endcan
                        
                        @can('ic_configurator.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ route('admin.ic-config.credential.index') }}">IC Configurator</a>
                        </li>
                        @endcan

                        @can('mmv_sync.list')
                         <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{url('api/car/getdata')}}" target="_blank">MMV Sync</a>
                        </li>
                        @endcan

                        @can('mmv_rto_data_sync.list')
                         <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{url('api/getRtoData')}}" target="_blank">MMV RTO Data Sync</a>
                        </li>
                        @endcan

                        @can('ic_return_url.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="ic-return-url" >IC Return URL</a>
                        </li>
                        @endcan

                        @can('kotak_encrypt_decrypt.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ url('admin/kotak-encrypt-decrypt') }}" >Kotak Response Decryption</a>
                        </li>
                        @endcan

                        @can('pa_insurance_masters.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ url('admin/pa-insurance-masters') }}" >PA Insurance Masters</a>
                        </li>
                        @endcan

                        @can('inspection_type.list')
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ url('admin/inspection') }}" >Inspection type</a>
                        </li>
                        @endcan

                        {{-- @can('inspection_type.list') --}}
                        <li class="link-cus-v1">
                            <a class="btn-cus-v3" href="{{ url('admin/boot-config') }}" >Config Boot</a>
                        </li>
                        {{-- @endcan --}}
                    </ul>
                    @else
                    @include('admin_lte.quicklink')
                    @endif
                </div>
            </div>
        </div>
@endsection('content')
@section('scripts')
<script>
    $(document).ready(function(){
        $('.btn').click(function(){
            window.location = $(this).data('url');
        });
    });
</script>
@endsection