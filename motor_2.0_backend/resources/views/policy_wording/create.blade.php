@extends('layout.app', ['activePage' => 'policy-wording', 'titlePage' => __('Policy Wording')])


@section('content')
    <main class="container-fluid">
        <section class="mb-4">
            <div class="card">
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif
                    <a href="#" onclick="history.back()" class="btn btn-primary btn-sm">Back</a>
                    <form action="{{ route('admin.policy-wording.store', rand()) }}" enctype="multipart/form-data"
                        method="post">@csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Company Name</label> 
                                    <select name="company_name" id="company_name" data-style="btn-primary" required
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="">Nothing selected</option>
                                        @foreach ($master_policies->unique('company_alias') as $key => $policy)
                                            <option {{ old('policy') == $policy->company_alias ? 'selected' : '' }}
                                                value="{{ $policy->company_alias }}">{{ $policy->company_alias }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Section</label>
                                    <select name="section" id="section" data-style="btn-primary" required
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="">Nothing selected</option>
                                        @foreach ($master_policies->unique('product_sub_type_code') as $key => $policy)
                                            <option
                                                {{ old('policy') == $policy->product_sub_type_code ? 'selected' : '' }}
                                                value="{{ $policy->product_sub_type_code }}">
                                                {{ $policy->product_sub_type_code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Business Type</label>
                                    <select name="business_type" id="business_type" data-style="btn-primary" required
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="">Nothing selected</option>
                                        <option value="newbusiness">newbusiness</option>
                                        <option value="rollover">rollover</option>
                                        <option value="third_party">third_party</option>
                                        <option value="own_damage">own_damage</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Policy Type</label>
                                    <select name="policy_type" id="policy_type" data-style="btn-primary" required
                                        class="selectpicker w-100" data-live-search="true">
                                        <option value="">Nothing selected</option>
                                        @foreach($master_premium_types as $key => $master_premium_type)
                                        <option value="{{ $master_premium_type->premium_type }}">{{ $master_premium_type->premium_type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="file" class="required">Select Policy Wording File :</label><br>
                                    <label class="btn btn-primary mb-0"></i>
                                        <input type="file" name="file" accept="application/pdf" required></label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success ">submit</button>

                    </form>

                </div>
            </div>
        </section>
    </main>

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Policy Wording - <span></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.policy-wording.store') }}" enctype="multipart/form-data" method="post">@csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="btn btn-primary btn-sm mb-0"></i><input type="file" name="policy_wording" required
                                    accept="application/pdf"></label>
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
                //$('#exampleModalLabel span').text($(this).attr('data'));
                $('input[name=policy_id]').val($(this).attr('data'));
            });

        });
    </script>
@endpush
