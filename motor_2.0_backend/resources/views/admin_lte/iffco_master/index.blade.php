
@if (session('message1'))
    <div class="alert alert-{{ session('iffco-class') }}">
        {{ session('message1') }}
    </div>
@endif
<div class="row">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title font-weight-bold">Iffco Master Download</h5><br>
                <form action="getIffcoMasterFile" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required mt-2">Select Section</label>
                                <select name="section" id="section" required data-style="btn-sm btn-primary" data-actions-box="true" class="form-control select2" data-live-search="true">
                                    <option value="">Nothing selected</option>
                                    <option value="pcv">PCV</option>
                                    <option value="car">Car</option>
                                    <option value="bike">Bike</option>
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
