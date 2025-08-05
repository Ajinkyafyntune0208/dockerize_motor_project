@extends('admin_lte.layout.app', ['activePage' => 'encrypt-decrypt', 'titlePage' => 'Encrypt Decrypt'])
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="card">
    <div class="row">
        <div class="col-12 text-uppercase mb-3 text-center">
            <h4 class="font-weight-bold">Encryption and Decryption</h4>
        </div>
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-lock"></i> ENCODE </h3>
                </div>

                <form action="" method="POST"> @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <input name= "type" type="hidden" value="encode">
                            <input type="text" class="form-control" id="normalId" name="normalId" placeholder="Enter Id here" value="{{ old('normalId', request()->normalId ?? null) }}" required>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Encode</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-unlock"></i> DECODE </h3>
                </div>
                <form action="" method="POST"> @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <input name="type" type="hidden" value="decode">
                            <input type="text" class="form-control" id="encodedId" name="encodedId" placeholder="Enter Id here" value="{{ old('encodedId', request()->encodedId ?? null) }}" required>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">Decode</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="row mb-5">
        <div class="col-md-12 col-lg-12">
                <div class="card-body" id="responseEnquiry">
                    <h4 class="card-title font-weight-bold text-uppercase">Response
                        @if (!empty($data['responseEnquiry']) && $data['responseEnquiry'] !== 'null')
                        &nbsp;&nbsp;<i class="fa fa-copy" data-type="responseEnquiryText"></i>
                        @endif
                    </h4>
                    @if (!empty($data['responseEnquiry']))
                    <pre id="responseEnquiryText">{{ $data['responseEnquiry'] }}</pre>
                    @endif
                </div>
        </div>
    </div>
</div>
<hr>
<div class="card">
    <div class="row">
        <div class="col-12 text-uppercase mb-3 text-center">
            <h4 class="font-weight-bold">Encrypt and Decrypt Request Response</h4>
        </div>
    
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"> <i class="fa fa-lock"></i>  Encryption</h3>
                @if (!empty(request()->normalText))
                &nbsp;&nbsp;<i class="fa fa-copy ml-2" data-type="normalText"></i>
                @endif
            </div>
            <form action="" method="POST"> @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="encryption">
                        <textarea class="form-control" id="normalText" name="normalText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('normalText', request()->normalText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                            <button class="btn btn-primary" type="submit">
                                Encrypt
                            </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
    <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-unlock"></i>  Decryption
                    @if (!empty(request()->encryptedText))
                        &nbsp;&nbsp;<i class="fa fa-copy" data-type="encryptedText"></i>
                    @endif
                </h3>
            </div>
            <form method="POST" action="">
                            @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="decryption">
                        <textarea class="form-control" id="encryptedText" name="encryptedText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('encryptedText', request()->encryptedText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                            <button class="btn btn-success" type="submit">
                                Decrypt
                            </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
    <div class="row">
            <div class="col-md-12 col-lg-12">
                    <div class="card-body" id="response">
                        <h4 class="card-title font-weight-bold text-uppercase">Response
                            @if (!empty($data['response']) && $data['response'] !== 'null')
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="responseText"
                                    role="button"></i>
                            @endif
                        </h4>
                        @if (!empty($data['response']))
                            <pre id="responseText">{{ $data['response'] }}</pre>
                        @endif
                    </div>
            </div>
        </div>
</div>
<hr>
<div class="card">
    <div class="row">
                <div class="col-12 text-uppercase mb-3 text-center">
            <h4 class="font-weight-bold">PII Data Encryption And Decryption</h4>
        </div>
    
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"> <i class="fa fa-lock"></i>  Encryption</h3>
            </div>
            <form action="" method="POST"> @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="piiEncrypt">
                        <textarea class="form-control" id="piiEncryptText" name="piiEncryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('piiEncryptText', request()->piiEncryptText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                            <button class="btn btn-primary" type="submit">
                                Encrypt
                            </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
    <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-unlock"></i>  Decryption
                </h3>
            </div>
            <form method="POST" action="">
                            @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="piiDecrypt">
                        <textarea class="form-control" id="piiDecryptText" name="piiDecryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('piiDecryptText', request()->piiDecryptText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-success" type="submit">
                            Decrypt
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
    <div class="row">
            <div class="col-md-12 col-lg-12">
                <div class="card-body" id="response">
                    <h4 class="card-title font-weight-bold text-uppercase">Response
                        @if (!empty($data['piiResponse']) && $data['piiResponse'] !== 'null')
                            &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="responseText"
                                role="button"></i>
                        @endif
                    </h4>
                    @if (!empty($data['piiResponse']))
                        <pre id="responseText">{{ $data['piiResponse'] }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>

<!-- payload encryption start-->
<div class="card">
    <div class="row">
                <div class="col-12 text-uppercase mb-3 text-center">
            <h4 class="font-weight-bold">PAYLOAD DATA ENCRYPT AND DECRYPT</h4>
        </div>
    
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"> <i class="fa fa-lock"></i>  Encryption</h3>
            </div>
            <form action="" method="POST"> @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="payloadEncrypt">
                        <textarea class="form-control" id="payloadEncryptText" name="payloadEncryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('payloadEncryptText', request()->payloadEncryptText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                            <button class="btn btn-primary" type="submit">
                                Encrypt
                            </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
    <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-unlock"></i>  Decryption
                </h3>
            </div>
            <form method="POST" action="">
                            @csrf
                <div class="card-body">
                    <div class="form-group">
                        <input name="type" type="hidden" value="payloadDecrypt">
                        <textarea class="form-control" id="payloadDecryptText" name="payloadDecryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('payloadDecryptText', request()->payloadDecryptText ?? null) }}</textarea>
                        
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-success" type="submit">
                            Decrypt
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
    <div class="row">
            <div class="col-md-12 col-lg-12">
                <div class="card-body" id="response">
                    <h4 class="card-title font-weight-bold text-uppercase">Response
                        @if (!empty($data['payloadResponse']) && $data['payloadResponse'] !== 'null')
                            &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="responseText"
                                role="button"></i>
                        @endif
                    </h4>
                    @if (!empty($data['payloadResponse']))
                        <pre id="responseText">{{ $data['payloadResponse'] }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
<!-- payload encryption end-->

</div>
@endsection
@section('scripts')
    @if (!empty($data['response']))
        <script>
            $(document).ready(function() {
                if ($(window).width() >= 1200) {
                    $('html,body').animate({
                        scrollTop: $('#response').offset().top - 100
                    }, 800);
                } else {
                    $('html,body').animate({
                        scrollTop: $('#response').offset().top - 32
                    }, 800);
                }
            });
        </script>
    @endif

    @if (!empty($data['response'] ?? $data['responseEnquiry'] ?? $data['piiResponse'] ?? $data['payloadResponse'] ?? null))
        <script>
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            $(document).ready(function() {
                $(document).on('click', '.fa.fa-copy', function() {
                    let id = $(this).data('type');

                    if (id == "encodedId" || id == "normalId") {
                        var text = $(`#${id}`).val();
                    } else {
                        var text = $(`#${id}`).text();
                    }

                    const elem = document.createElement('textarea');
                    elem.value = text;
                    document.body.appendChild(elem);
                    elem.select();
                    document.execCommand('copy');
                    document.body.removeChild(elem);
                    alert('Text copied...!')
                });
            });
        </script>
    @endif
@endsection