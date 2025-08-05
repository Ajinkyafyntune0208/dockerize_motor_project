
@if (session('message2'))
    <div class="alert alert-{{ session('bajaj-class') }}">
        {{ session('message2') }}
    </div>
@endif
<div class="row">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title font-weight-bold">Bajaj Master Download</h5><br>
                <form action="getBajajFile" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required mt-2">Select Section</label>
                                <select name="section" id="section" required data-style="btn-sm btn-primary" data-actions-box="true" class="form-control select2" data-live-search="true">
                                    <option value="">Nothing selected</option>
                                    <option value="bike">Bike</option>
                                    <option value="car">Car</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required mt-2">Master Type</label>
                                <select name="master_type" id="business_type" required data-style="btn-sm btn-primary" data-actions-box="true" class="form-control select2" data-live-search="true">
                                    <option value="">Nothing selected</option>
                                    <option value="getMMV">Get MMV</option>
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
