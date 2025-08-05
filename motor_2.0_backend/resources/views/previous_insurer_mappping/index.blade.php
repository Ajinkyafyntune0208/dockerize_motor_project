@extends('layout.app', ['activePage' => 'previous-insurer-mapping', 'titlePage' => __('Previous Insurer Mapping')])
@section('content')

<style>

    #rto_zone, #rto_status, #rto_state{
        background-color: #ffffff!important;
        color : #000000!important;
    }

    @media (min-width: 576px){
        .modal-dialog {
            max-width: 911px;
            margin: 34px auto;
            word-wrap: break-word;
        }
    }

</style>
@if (session('status'))
    <div class="alert alert-{{ session('class') }}">
        {{ session('status') }}
    </div>
@endif
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Previous Insurer Mapping</h5>
                </div>
        <div class="modal-body">
<div class="modal-body">
    <form method="post" action="" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                <div class="form-group">
                    <input type="file" name="previous_insurer_mapping" required/>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                <div class="form-group">
                    <button class="btn btn-primary">Upload</button>
                    <a class="btn btn-primary" href="{{ url('/admin/export-users') }}">Export</a>
                </div>
            </div>
            
        </div>
    </form>
    <div class="row">
        @if(!empty($data))
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="response_log">
                            <thead>
                                <tr>
                                    <th>Previous Insurer</th>
                                    <th>Company Alias</th>
                                    <th>Oriental</th>
                                    <th>Acko</th>
                                    <th>Sbi</th>
                                    <th>Bajaj Allianz</th>
                                    <th>Bharti Axa</th>
                                    <th>Cholamandalam</th>
                                    <th>DHFL</th>
                                    <th>Edelweiss</th>
                                    <th>Future Generali</th>
                                    <th>Godigit</th>
                                    <th>Hdfc Ergo</th>
                                    <th>Hdfc</th>
                                    <th>Hdfc Ergo Gic</th>
                                    <th>Icici Lombard</th>
                                    <th>Iffco Tokio</th>
                                    <th>Kotak</th>
                                    <th>Liberty Videocon</th>
                                    <th>Magma</th>
                                    <th>National Insurance</th>
                                    <th>Raheja</th>
                                    <th>Reliance</th>
                                    <th>Royal Sundaram</th>
                                    <th>Shriram</th>
                                    <th>Tata Aig</th>
                                    <th>United India</th>
                                    <th>Universal Sompo</th>
                                    <th>New India</th>
                                    <th>Nic</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data as $key => $row)
                                <tr>
                                    <td>{{ $row->previous_insurer }}</td>
                                    <td>{{ $row->company_alias}}</td>
                                    <td>{{$row->oriental }}</td>
                                    <td>{{$row->acko }}</td>
                                    <td>{{ $row->sbi}}</td>
                                    <td>{{ $row->bajaj_allianz}}</td>
                                    <td>{{ $row->bharti_axa}}</td>
                                    <td>{{ $row->cholamandalam}}</td>
                                    <td>{{ $row->dhfl}}</td>
                                    <td>{{ $row->edelweiss}}</td>
                                    <td>{{ $row->future_generali}}</td>
                                    <td>{{ $row->godigit}}</td>
                                    <td>{{ $row->hdfc_ergo}}</td>
                                    <td>{{ $row->hdfc}}</td>
                                    <td>{{ $row->hdfc_ergo_gic}}</td>
                                    <td>{{ $row->icici_lombard}}</td>
                                    <td>{{ $row->iffco_tokio}}</td>
                                    <td>{{ $row->kotak}}</td>
                                    <td>{{ $row->liberty_videocon}}</td>
                                    <td>{{ $row->magma}}</td>
                                    <td>{{ $row->national_insurance}}</td>
                                    <td>{{ $row->raheja}}</td>
                                    <td>{{ $row->reliance}}</td>
                                    <td>{{ $row->royal_sundaram}}</td>
                                    <td>{{ $row->shriram}}</td>
                                    <td>{{ $row->tata_aig}}</td>
                                    <td>{{ $row->united_india}}</td>
                                    <td>{{ $row->universal_sompo}}</td>
                                    <td>{{ $row->new_india}}</td>
                                    <td>{{ $row->nic}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
        </div>
    </div>
    </section>

    </div>
</div>
</main>
           

    {{-- Upload  --}}
   
@endsection
@push('scripts')
<script>

setTimeout(() => {
        $('.alert-success').css('display', 'none');
    }, 2000);

</script>
@endpush