@extends('layout.app', ['activePage' => 'user-journey-activity', 'titlePage' => __('Clear User Session')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Clear User Activity Session</h5>
                    <div class="col-6 col-md-4 text-center">
                        @if (session('error'))
                        <div class="alert alert-danger mt-3 py-1">
                            {{ session('error') }}
                        </div>
                        @endif
                
                        @if (session('success'))
                        <div class="alert alert-success mt-3 py-1">
                            {{ session('success') }}
                        </div>
                        @endif
                    </div>
                    <div class="form-group">
                        <form method="post">
                            @csrf
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <Label class="required">Enquiry Id</Label>
                                        <input type="text" name="enquiryId" value="{{ old('enquiryId', request()->enquiryId) }}" required class="form-control" placeholder="Enquiry ID" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex align-items-center h-100">
                                        <input type="submit" class="btn btn-outline-info btn-sm w-100" value="Clear">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
