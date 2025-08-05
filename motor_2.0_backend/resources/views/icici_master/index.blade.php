@extends('layout.app', ['activePage' => 'icici-master-download', 'titlePage' => __('icici master')])
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Icici Master Download</h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                        <form action="getfile" method="POST">
                            @csrf 
                            <div class="row">     
                                <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required">Select Section</label>
                                            <select name="section" id="section" required data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                                <option value="">Nothing selected</option>
                                                <option value="car">Car</option>
                                                <option value="bike">Bike</option>
                                                <option value="cv">Cv</option>
                                                <option value="gcv">Gcv</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required">Master Type</label>
                                            <select name="master_type" id="business_type" required data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" >
                                            <option value="">Nothing selected</option>
                                            <option value="GetVehicleRTOMasterDetails">GetVehicleRTOMasterDetails</option>
                                            <option value="GetVehicleMasterDetails">GetVehicleMasterDetails</option>
                                            </select>
                                        </div>
                                    </div>
                            </div>
                            <button class="btn btn-success">Get File</button>
                        </form>
                </div>
            </div>
        </div>
    </div>
    @include('bajaj_master.index')
</div> <!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Policy Wording - <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.policy-wording.store') }}" enctype="multipart/form-data" method="post">@csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="btn btn-primary btn-sm mb-0"></i><input type="file" name="policy_wording" required accept="application/pdf"></label>
                        <input type="text" hidden name="policy_id" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#policy_wording_table').DataTable();
        $(document).on('click', '.change-policy-wording', function() {
            $('#exampleModalLabel span').text($(this).attr('data'));
            $('input[name=policy_id]').val($(this).attr('data'));
        });

    });
</script>
@endpush