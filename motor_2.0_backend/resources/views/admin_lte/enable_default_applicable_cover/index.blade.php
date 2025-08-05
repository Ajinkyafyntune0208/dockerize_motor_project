@extends('admin_lte.layout.app', ['activePage' => 'user', 'titlePage' => __('Enable Default Applicable Cover')])

@section('content')

<style>
    #rto_zone, #rto_status, #rto_state {
        background-color: #ffffff!important;
        color: #000000!important;
    }
    @media (min-width: 576px) {
        .modal-dialog {
            max-width: 911px;
            margin: 34px auto;
            word-wrap: break-word;
        }
    }
</style>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">

                <div class="card-body">

                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form action="{{ route('admin.ic-config.cover_store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6">

                                <div class="d-flex gap-4">
                                    <div class="form-check col-md-4 form-group">
                                        <input class="form-check-input" type="checkbox" name="covers[]" value="bike" {{ in_array('bike', $selectedCovers) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bike">Bike</label>
                                    </div>

                                    <div class="form-check col-md-4 form-group">
                                        <input class="form-check-input" type="checkbox" name="covers[]" value="car" {{ in_array('car', $selectedCovers) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="car">Car</label>
                                    </div>

                                    <div class="form-check col-md-4 form-group">
                                        <input class="form-check-input" type="checkbox" name="covers[]" value="pcv" {{ in_array('pcv', $selectedCovers) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="pcv">PCV</label>
                                    </div>

                                    <div class="form-chec col-md-4 form-groupk">
                                        <input class="form-check-input" type="checkbox" name="covers[]" value="gcv" {{ in_array('gcv', $selectedCovers) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="gcv">GCV</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </div>
                        <br><br>
                    </form>


                </div>

            </div>
        </div>
    </div>
</div>

@endsection
