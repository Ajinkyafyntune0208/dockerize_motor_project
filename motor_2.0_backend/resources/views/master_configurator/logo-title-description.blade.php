<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <h4 class="form-tab">Broker Title Configration</h4>
                    <form id="dataForm" action="{{ route('admin.config-onboarding.save-title-data') }}" method="POST" class="">
                        @csrf
                        <div class="row">
                            <!-- Generic Section -->
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="genericTitle">Generic Title</label>
                                            <input type="text" id="genericTitle" name="generic[title]" class="form-control form-control-sm" required>
                                        </div>
                                    </div>
                                    {{-- <div class="col-2 col-md-1">
                                        <a class="btn btn-xs btn-primary btn-outline view-data" data-target="#genericTitle">View</a>
                                    </div> --}}
                                </div>
                            </div>
                    
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="genericDescription">Generic Description</label>
                                            <textarea required id="genericDescription" name="generic[description]" rows="3" cols="28" 
                                                class="form-control form-control-sm" style="height: 60px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <!-- Car Section -->
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="carTitle">Car Title</label>
                                            <input required type="text" id="carTitle" name="car[title]" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="carDescription">Car Description</label>
                                            <textarea required id="carDescription" name="car[description]" rows="3" cols="28"
                                                class="form-control form-control-sm" style="height: 60px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <!-- Bike Section -->
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="bikeTitle">Bike Title</label>
                                            <input required type="text" id="bikeTitle" name="bike[title]" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="bikeDescription">Bike Description</label>
                                            <textarea required id="bikeDescription" name="bike[description]" rows="3" cols="28"
                                                class="form-control form-control-sm" style="height: 60px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <!-- CV Section -->
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="cvTitle">CV Title</label>
                                            <input required type="text" id="cvTitle" name="cv[title]" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="cvDescription">CV Description</label>
                                            <textarea required id="cvDescription" name="cv[description]" rows="3" cols="28" 
                                                class="form-control form-control-sm" style="height: 60px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                        <div class="row">
                            <div class="d-flex justify-content-between" style="margin-bottom: ;">
                                <button type="submit" id="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('admin1/js/broker-config/title-config.js') }}"></script>
