<div class="row">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Bajaj Master Download</h5>
                @if (session('message'))
                    <div class="alert alert-{{ session('bajaj-class') }}">
                        {{ session('message') }}
                    </div>
                @endif
                <form action="getBajajFile" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Select Section</label>
                                <select name="section" id="section" required data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                    <option value="">Nothing selected</option>
                                    <option value="bike">Bike</option>
                                    <option value="car">Car</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Master Type</label>
                                <select name="master_type" id="business_type" required data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
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
