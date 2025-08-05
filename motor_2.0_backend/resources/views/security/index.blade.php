@extends('layout.app', ['activePage' => 'Encryption/Decryption', 'titlePage' => __('Encryption and Decryption')])
@section('content')
    <div class="content-wrapper">

        @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">

            <div class="text-uppercase mb-3 text-center">
                <h3 class="font-weight-bold">Encode and Decode Enquiry Id</h3>
            </div>

            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title text-uppercase required"> <i class="fa fa-lock"></i>&nbsp;&nbsp; Encode
                            @if (!empty(request()->normalId))
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="normalId" role="button"></i>
                            @endif
                        </h4>

                        <form action="" method="POST">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="encode">
                                <input class="form-control" id="normalId" name="normalId" name="normalId" type="text"
                                    value="{{ old('normalId', request()->normalId ?? null) }}" placeholder="Enter Id here" required>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Encode Enquiry Id'>
                                        Encode
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="text-uppercase card-title required"> <i class="fa fa-unlock-alt"></i>&nbsp;&nbsp; Decode
                            @if (!empty(request()->encodedId))
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="encodedId" role="button"></i>
                            @endif
                        </h4>

                        <form method="POST" action="">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="decode">
                                <input class="form-control" id="encodedId" name="encodedId" name="encodedId" type="text"
                                    value="{{ old('encodedId', request()->encodedId ?? null) }}"
                                    placeholder="Enter Id here" required>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Decoce Enquiry Id'>
                                        Decode
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12 col-lg-12">
                <div class="card" id="responseEnquiry">
                    <div class="card-body">
                        <h4 class="card-title text-uppercase">Response
                            @if (!empty($data['responseEnquiry']) && $data['responseEnquiry'] !== 'null')
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="responseEnquiryText"
                                    role="button"></i>
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
        <div class="row">
            <div class="col-12 text-uppercase mb-3 text-center">
                <h3 class="font-weight-bold">Encrypt and Decrypt Request Response</h3>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title text-uppercase required"> <i class="fa fa-lock"></i>&nbsp;&nbsp; Encryption
                            @if (!empty(request()->normalText))
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="normalText" role="button"></i>
                            @endif
                        </h4>

                        <form action="" method="POST">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="encryption">
                                <textarea id="normalText" name="normalText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('normalText', request()->normalText ?? null) }}</textarea>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Encrypt'>
                                        Encrypt
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="text-uppercase card-title required"> <i class="fa fa-unlock-alt"></i>&nbsp;&nbsp; Decryption
                            @if (!empty(request()->encryptedText))
                                &nbsp;&nbsp;<i class="text-info fa fa-copy ml-2" data-type="encryptedText"
                                    role="button"></i>
                            @endif
                        </h4>

                        <form method="POST" action="">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="decryption">
                                <textarea id="encryptedText" name="encryptedText" style="width:100%"
                                    placeholder="Type or paste your input string here..." rows="15" required>{{ old('encryptedText', request()->encryptedText ?? null) }}</textarea>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Decrypt'>
                                        Decrypt
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 col-lg-12">
                <div class="card" id="response">
                    <div class="card-body">
                        <h4 class="card-title text-uppercase">Response
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

        <div class="row">
            <div class="col-12 text-uppercase my-3 text-center">
                <h3 class="font-weight-bold">PII Data Encryption And Decryption</h3>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title text-uppercase required"> <i class="fa fa-lock"></i>&nbsp;&nbsp; Encryption
                            
                        </h4>

                        <form action="" method="POST">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="piiEncrypt">
                                <textarea id="piiEncryptText" name="piiEncryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('piiEncryptText', request()->piiEncryptText) }}</textarea>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Encrypt'>
                                        Encrypt
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="text-uppercase card-title required"> <i class="fa fa-unlock-alt"></i>&nbsp;&nbsp; Decryption</h4>

                        <form method="POST" action="">
                            @csrf
                            <div class="col-md-12 col-lg-12">
                                <input name="type" type="hidden" value="piiDecrypt">
                                <textarea id="piiDecryptText" name="piiDecryptText" style="width:100%" placeholder="Type or paste your input string here..."
                                    rows="15" required>{{ old('piiDecryptText', request()->piiDecryptText) }}</textarea>
                                <div class="mt-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit" title='Decrypt'>
                                        Decrypt
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 col-lg-12">
                <div class="card" id="response">
                    <div class="card-body">
                        <h4 class="card-title text-uppercase">Response
                            @if (!empty($data['piiResponse']))
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

    </div>

@endsection

@push('scripts')
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

    @if (!empty($data['response'] ?? $data['responseEnquiry'] ?? $data['piiResponse'] ?? null))
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

@endpush
