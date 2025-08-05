@extends('admin_lte.layout.app', ['activePage' => 'user-journey-activity', 'titlePage' => __('User Activity Session')])
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Clear User Activity Session</h3>
    </div>
    <div class="card-body">
        <form method="post">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group row">
                        <Label class="col-sm-2 col-form-label">Enquiry Id</Label>
                        <div class="col-sm-10">
                            <input type="text" name="enquiryId" value="{{ old('enquiryId', request()->enquiryId) }}" required class="form-control" placeholder="Enquiry ID" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="d-flex">
                        <input type="submit" class="btn btn-primary" value="Clear">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection('content')