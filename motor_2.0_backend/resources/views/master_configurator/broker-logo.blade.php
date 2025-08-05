@php
    $logoImage = $brokerConfigAsset->where('key', 'logo')->pluck('value')->first();
    $logoImage = $logoImage['base64'] ?? null;
    $faviconImage = $brokerConfigAsset->where('key', 'favicon')->pluck('value')->first();
    $faviconImage = $faviconImage['base64'] ?? null;
@endphp
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <h4 class="form-tab">Broker Logo Configurator</h4>
                    <form method="POST" name="logoForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="brokerLogo">
                                                Broker Logo
                                            </label>
                                            <input type="file" name="brokerLogo" id="brokerLogo" class="form-control form-control-sm" accept="image/*">
                                        </div>
                                    </div>
                                    @if (!empty($logoImage))
                                        <div class="col-2 col-md-1">
                                            <a class="btn btn-xs btn-primary btn-outline" data-modal-title="Broker Logo" data-src="{{$logoImage}}" onclick="viewClicked(this)">view</a>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="brokerFavicon">Broker Favicon</label>
                                            <input type="file" id="brokerFavicon" name="brokerFavicon" class="form-control form-control-sm" accept="image/*">
                                        </div>
                                    </div>
                                    @if (!empty($faviconImage))
                                        <div class="col-2 col-md-1">
                                            <a class="btn btn-xs btn-primary btn-outline" data-modal-title="Favicon" data-src="{{$faviconImage}}" onclick="viewClicked(this)">view</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="d-flex justify-content-between" style="margin-bottom: ;">
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
  
  <!-- Modal -->
  <div class="modal fade" id="brokerLogoModal" tabindex="-1" role="dialog" aria-labelledby="brokerLogoModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title broker-logo-title" id="brokerLogoModalLongTitle">Modal title</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeModal()">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row justify-content-center align-items-center">
            <div class="col-md-6 col-lg-5">
                <img class="broker-logo-img" src="" style="width:100%">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<script>
    const brokerLogoUrl = "{{route('admin.config-onboarding.broker-logo')}}";
</script>
<script src="{{asset('admin1/js/broker-config/logo-config.js')}}"></script>