@extends('admin_lte.layout.app', ['activePage' => 'kotak-ckyc-response-decrypt', 'titlePage' => 'Kotak CKYC Response Decrypt'])
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
            <h4 class="font-weight-bold">Kotak CKYC Response Decryption</h4>
        </div>

        <div class="col-md-12">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-unlock"></i> DECODE </h3>
                </div>
                <form action="kotak-encrypt-decrypt" method="POST"> @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <input name="type" type="hidden" value="decode">
                            <input type="text"  class="form-control" id="encodedId" name="encodedId" placeholder="Enter text to decode here" value="{{ old('encodedId', request()->encodedId ?? null) }}" required>
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
                        @if (!empty($data) && $data !== 'null')
                        &nbsp;&nbsp;<i class="fa fa-copy" data-type="responseEnquiryText"></i>
                        @endif
                    </h4><br><br>
                    @if (!empty($data))
                    <p id="responseEnquiryText">{{ $data}}</p>
                    @endif
                </div>
        </div>
    </div>
</div>
<hr>

<hr>

@endsection
@section('scripts')
    @if (!empty($data))
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

    @if (!empty($data)  ?? null)
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