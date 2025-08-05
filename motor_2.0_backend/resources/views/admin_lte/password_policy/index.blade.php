@extends('admin_lte.layout.app', ['activePage' => 'Password Policy', 'titlePage' => __('Password Policy')])
@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('admin.password-policy.store') }}" method="POST">@csrf
      <div class="row">
        <div class="col-sm-6">
          <div class="form-group">
            <label class="required">Minimum Password length</label>
            <input type="text"  name="password.minLength" class="form-control" value="{{$data['password.minLength']}}" required>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                <input type="checkbox" name="password.upperCaseLetter" class="custom-control-input" id="customSwitch3" value="Y" {{$data['password.upperCaseLetter'] == 'Y' ? 'checked' : ''}}>
                <label class="custom-control-label" for="customSwitch3">Required at least one uppercase letter</label>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                <input type="checkbox" name="password.lowerCaseLetter" class="custom-control-input" id="customSwitch3" value="Y" {{$data['password.lowerCaseLetter'] == 'Y' ? 'checked' : ''}}>
                <label class="custom-control-label" for="customSwitch3">Required at least one lowercase letter</label>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                <input type="checkbox" name="password.atLeastOneNumber" class="custom-control-input" id="customSwitch3" value="Y" {{$data['password.atLeastOneNumber'] == 'Y' ? 'checked' : ''}}>
                <label class="custom-control-label" for="customSwitch3">Required at least one number</label>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                <input type="checkbox" name="password.atLeastOneSymbol" class="custom-control-input" id="customSwitch3" value="Y" {{$data['password.atLeastOneSymbol'] == 'Y' ? 'checked' : ''}}>
                <label class="custom-control-label" for="customSwitch3">Required at least one symbol charecter</label>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                <input type="checkbox" name="password.policy.mail.enable" class="custom-control-input" id="customSwitch3" value="Y" {{$data['password.policy.mail.enable'] == 'Y' ? 'checked' : ''}}>
                <label class="custom-control-label" for="customSwitch3">Password policy mail enable</label>
            </div>
          </div>
          <div class="form-group">
            <div class="form-group">
              <label class="required">Notification of password expiry for</label>
              <input type="text"  name="password.notificationExpireInDays" class="form-control" value="{{$data['password.notificationExpireInDays'] ?? 7}}" required><label>days</label>
            </div>
          </div>
          <div class="form-group">
            <div class="form-group">
              <label class="required">Password expire in</label>
              <input type="text"  name="password.passExpireInDays" class="form-control" value="{{$data['password.passExpireInDays'] ?? 7}}" required><label>days</label>
            </div>
          </div>
          <div class="form-group">
            <div class="form-group">
              <label class="required">Password link expire in</label>
              <input type="text"  name="password.linkExpireInDays" class="form-control" value="{{$data['password.linkExpireInDays'] ?? 7}}" required><label>days</label>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save</button>
    </form>
  </div>
</div>
@endsection('content')