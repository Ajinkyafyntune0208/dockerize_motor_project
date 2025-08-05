@extends('admin_lte.layout.app', ['activePage' => 'hide_pyi', 'titlePage' => __('Show Previous Insurers on Quote Landing Page')])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('admin.hide_pyi.store') }}" method="post" id="hidePYI">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group w-100">
                                    <label class="required">Journey Type</label>
                                    <select name="seller_type" class="form-control selectpicker" data-live-search="true" required>
                                        <option value="">Nothing selected</option>
                                        <option value="B2B">B2B</option>
                                        @if (config('constants.motorConstant.IS_USER_ENABLED') == 'Y')
                                            <option value="B2C">B2C</option>
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group w-100">
                                    <label class="required">Status</label>
                                    <select name="status" class="form-control" required>
                                        <option value="Y">Yes</option>
                                        <option value="N">No</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success btn-md float-right">Submit</button>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <h5>Current Settings:</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Seller Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['B2B', 'B2C'] as $type)
                                <tr>
                                    <td>{{ $type }}</td>
                                    <td>{{ $toggles[$type]->status ?? 'Not Set' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
