@extends('layout.app', ['activePage' => 'Password Policy', 'titlePage' => __('Password Policy')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Password Policy</h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <form action="{{ route('admin.password-policy.store') }}" method="POST">@csrf
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                              <label for="min" class="col-form-label required">Minimum Password length</label>
                            </div>
                            <div class="col-auto">
                              <input type="text"  name="data[min_pass][value]" class="form-control" value="{{$data['password.minLength']}}" required>
                              <input type="hidden" name="data[min_pass][label]" value="Minimum password length">
                              <input type="hidden" name="data[min_pass][key]" value="password.minLength">
                            </div>
                          </div>
                          <div class="row g-3 align-items-center" >
                            <div class="col-auto">
                              <div class="form-check form-switch">
                              <input type="hidden" name="data[upper_pass][value]" value="N">
                              <input class="form-check-input" style="margin-left:225px;" name= "data[upper_pass][value]"  value="Y" type="checkbox" role="switch"  id="uppercase"  {{$data['password.upperCaseLetter'] == 'Y' ? 'checked' : ''}}>
                              <input type="hidden" name="data[upper_pass][label]" value="Uppercase letter">
                              <input type="hidden" name="data[upper_pass][key]" value="password.upperCaseLetter">
                                </div>
                            </div>
                              <div class="col-auto">
                              <label for="inputPassword6" class="col-form-label ">Required at least one uppercase letter</label>
                            </div> 
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                  <input type="hidden" name="data[lower_pass][value]" value="N">
                                  <input class="form-check-input" name= "data[lower_pass][value]" style="margin-left:225px;" value="Y" type="checkbox" role="switch"  id="lowercase"   {{$data['password.lowerCaseLetter'] == 'Y' ? 'checked' : ''}}>
                                  <input type="hidden" name="data[lower_pass][label]" value="Lowercase letter">
                                  <input type="hidden" name="data[lower_pass][key]" value="password.lowerCaseLetter">
                                </div>
                            </div>
                            <div class="col-auto">
                              <label for="inputPassword6" class="col-form-label ">Required at least one lowercase letter</label>
                            </div> 
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                  <input type="hidden" name="data[one_pass][value]" value="N">
                                  <input class="form-check-input" style="margin-left:225px;" value="Y" type="checkbox" role="switch" id="one_number"  name= "data[one_pass][value]" {{$data['password.atLeastOneNumber'] == 'Y' ? 'checked' : ''}}>
                                  <input type="hidden" name="data[one_pass][label]" value="At least one number">
                                  <input type="hidden" name="data[one_pass][key]" value="password.atLeastOneNumber">
                                </div>
                            </div>
                              <div class="col-auto">
                              <label for="inputPassword6" class="col-form-label ">Required at least one number</label>
                            </div> 
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                  <input type="hidden" name="data[symobl_pass][value]" value="N">
                                  <input class="form-check-input" style="margin-left:225px;" value="Y" type="checkbox" role="switch" id="one_symbol"  name= "data[symobl_pass][value]" {{$data['password.atLeastOneSymbol'] == 'Y' ? 'checked' : ''}}>
                                  <input type="hidden" name="data[symobl_pass][label]" value="At least one symbol">
                                  <input type="hidden" name="data[symobl_pass][key]" value="password.atLeastOneSymbol">
                                </div>
                            </div>
                              <div class="col-auto">
                              <label for="inputPassword6" class="col-form-label ">Required at least one symbol charecter</label>
                            </div> 
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                  <input type="hidden" name="data[mail_enable][value]" value="N">
                                  <input class="form-check-input" style="margin-left:225px;" value="Y" type="checkbox" role="switch" id="one_symbol"  name= "data[mail_enable][value]" {{$data['password.policy.mail.enable'] == 'Y' ? 'checked' : ''}}>
                                  <input type="hidden" name="data[mail_enable][label]" value="Password policy mail enable">
                                  <input type="hidden" name="data[mail_enable][key]" value="password.policy.mail.enable">
                                </div>
                            </div>
                              <div class="col-auto">
                              <label for="inputPassword6" class="col-form-label ">Password policy mail enable</label>
                            </div> 
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                              <label for="" class="col-form-label required">Notification of password expiry for</label>
                            </div>
                            <div class="col-auto">
                              <input type="text"  name="data[notification_expire_password][value]" class="form-control" value="{{$data['password.notificationExpireInDays'] ?? 7}}"required>
                              <input type="hidden" name="data[notification_expire_password][label]" value="Notification of password expiry indays">
                              <input type="hidden" name="data[notification_expire_password][key]" value="password.notificationExpireInDays">
                            </div>
                            <div class="col-auto">
                                <label  class="col-form-label">days</label>
                              </div>
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                              <label for="" class="col-form-label required">Password expire in</label>
                            </div>
                            <div class="col-auto" style="margin-left:115px">
                              <input type="text"  name="data[expire_pass][value]" class="form-control" value="{{$data['password.passExpireInDays'] ?? 7}}"required>
                              <input type="hidden" name="data[expire_pass][label]" value="Password expire in days">
                              <input type="hidden" name="data[expire_pass][key]" value="password.passExpireInDays">
                            </div>
                            <div class="col-auto">
                                <label  class="col-form-label">days</label>
                              </div>
                          </div>
                          <div class="row g-3 align-items-center">
                            <div class="col-auto">
                              <label for="" class="col-form-label required">Password link expire in</label>
                            </div>
                            <div class="col-auto" style="margin-left:85px">
                              <input type="text"  name="data[expire_link][value]" class="form-control" value="{{$data['password.linkExpireInDays'] ?? 3}}" required>
                              <input type="hidden" name="data[expire_link][label]" value="Link expire in days">
                              <input type="hidden" name="data[expire_link][key]" value="password.linkExpireInDays">
                            </div>
                            <div class="col-auto">
                                <label  class="col-form-label">days</label>
                              </div>
                          </div>
                          <div class="d-grid gap-2 d-md-flex justify-content-md-end"> 
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')

</script>
@endpush