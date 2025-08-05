@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">SMS Email Template</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <a href="{{ url()->previous() }}" class="btn btn-sm btn-primary"><i class="fa fa-arrow-circle-left"></i> Back</a>
                </div>
            </div>

            @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
            @endif
            <form action="{{ route('admin.email-sms-template.update', $emailSmsTemplate) }}" method="post">@csrf @method('PUT')
                <div class="form-group">
                    <label for="">Email SMS Name</label>
                    <input type="text" name="email_sms_name" class="form-control @error('email_sms_name') is-invalid @enderror" placeholder="Email SMS Name" value="{{ old('email_sms_name', $emailSmsTemplate->email_sms_name) }}">
                    @error('email_sms_name') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="">Type</label>
                    <select name="type" class="form-control @error('type') is-invalid @enderror">
                        <option value="" disabled selected>Select Any One</option>
                        <option {{ old('type', $emailSmsTemplate->type) == 'email' ? 'selected' : '' }} value="email">Email</option>
                        <option {{ old('type', $emailSmsTemplate->type) == 'sms' ? 'selected' : '' }} value="sms">SMS</option>
                    </select>
                    <!-- <input type="text" class="form-control @error('type') is-invalid @enderror" placeholder="Type" value="{{ old('type') }}"> -->
                    @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="">Subject</label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" placeholder="Subject" value="{{ old('subject', $emailSmsTemplate->subject) }}">
                    @error('subject') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="">Variable</label>
                    <input type="text" name="variable" class="form-control @error('variable') is-invalid @enderror" placeholder="Variable" data-role="tagsinput" value="{{ old('variable', $emailSmsTemplate->variable ? implode(',' , $emailSmsTemplate->variable) : '') }}">
                    @error('variable') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="">Body</label>
                    <small class="form-text text-muted">variable name should be * <b>##var_name##</b> * as</small>
                    <textarea id="editor" name="body" class="form-control @error('body') is-invalid @enderror" placeholder="Body">{{ old('body', $emailSmsTemplate->body) }}</textarea>
                    <div id="output" class="mt-3">
                        <iframe id="result" style="width: 100%; min-height: 300px;"></iframe>
                    </div>
                </div>
                @error('body') <span class="text-danger">{{ $message }}</span> @enderror

                <div class="form-group">
                    <label for="">Status</label>
                    <select name="status" class="form-control @error('status') is-invalid @enderror">
                        <option value="" disabled selected>Select Any One</option>
                        <option {{ old('status', $emailSmsTemplate->status) == 'active' ? 'selected' : '' }} value="active">Active</option>
                        <option {{ old('status', $emailSmsTemplate->status) == 'inactive' ? 'selected' : '' }} value="inactive">Inactive</option>
                    </select>
                    @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"> Submit</i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {

        var validation = false;
        var myTimeoutId = null;


        $('#info').onclick = function() {
            $('#console').style.display = "block";
            console.log("hello");
        }
        $('#close').onclick = function() {
            $('#console').style.display = "none";
        }

        var config = {
            mode: "text/html",
            extraKeys: {
                "Ctrl-Space": "autocomplete"
            },
            lineNumbers: true,
            keyMap: "sublime",
            tabSize: 4,
        };
        // initialize editor
        var editor = CodeMirror.fromTextArea(document.getElementById('editor'), config);
        editor.setOption("theme", "material-ocean");

        function loadHtml(html) {
            const document_pattern = /( )*?document\./i;
            let finalHtml = html.replace(document_pattern, "document.getElementById('result').contentWindow.document.");
            $('#result').contents().find('html').html(finalHtml);
        }

        loadHtml($('#editor').val());

        editor.on('change', function(cMirror) {

            if (myTimeoutId !== null) {
                clearTimeout(myTimeoutId);
            }
            myTimeoutId = setTimeout(function() {

                try {

                    loadHtml(cMirror.getValue());

                } catch (err) {

                    console.log('err:' + err);

                }


            }, 1000);

        });

    });
</script>
@endpush