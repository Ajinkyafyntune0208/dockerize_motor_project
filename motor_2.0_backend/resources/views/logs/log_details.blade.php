<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="{{ asset('js/jquery.min.js') }}"></script>
        <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

        <title>Log Request-Response</title>

        <style>
            textarea {
            width:100%;
            padding: 12px 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f8f8f8;
            font-size: 13px;
            resize: vertical;
            }
            label{
                font-weight: bold;
            }
            input{
                width: 87%;
                padding: 8px 10px;
                box-sizing: border-box;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 12px;
            }
            .button,select{
                position: relative;
                width: 6%;
                height: 42%;
                padding: 8px 4px;
                box-sizing: border-box;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-weight: bold;
            }
            div {
                padding-top: 3px;
                padding-bottom: 5px;
            }
            .btn:active {
                transform: scale(0.96);
                box-shadow: 3px 2px 22px 1px rgba(8, 8, 8, 0.24);
            }
            body {
                background-color:rgb(237, 235, 231);
                font: 14px sans-serif;
                color: darkslateblue;
                border: 5px;
                margin: 10px;
                padding: 10px;
            }
            .button--loading .button__text {
              visibility: hidden;
              opacity: 0;
            }
            .button--loading::after {
              content: "";
              position: absolute;
              width: 16px;
              height: 16px;
              top: 0;
              left: 0;
              right: 0;
              bottom: 0;
              margin: auto;
              border: 4px solid transparent;
              border-top-color: #ffffff;
              border-radius: 50%;
              animation: button-loading-spinner 1s ease infinite;
            }
            #request_headers{
                height: {{40+ (($count - 1)*20)}}px;
            }
            @keyframes button-loading-spinner {
              from {
                transform: rotate(0turn);
              }
              to {
                transform: rotate(1turn);
              }
            }
            .btn-custom {
                background: #1F3BB3;
                color: #fff;
                float: right;
                margin-right: 10px;
                margin-bottom: 5px;
            }
            .d-none{
                display: none
            }
        </style>
    </head>
    <body>
        <div class="container">
            <form id="cf-form" action="#" >
                <div>
                    <select name="method" value="">
                        <option {{ old('method', $log->method) == 'get' ? 'selected' : '' }} value="GET">GET</option>
                        <option {{ old('method', $log->method) == 'post' ? 'selected' : '' }} value="POST">POST</option>
                        <option {{ old('method', $log->method) == 'delete' ? 'selected' : '' }} value="DELETE">DELETE</option>
                        <option {{ old('method', $log->method) == 'put' ? 'selected' : '' }} value="PUT">PUT</option>
                        <option {{ old('method', $log->method) == 'patch' ? 'selected' : '' }} value="PATCH">PATCH</option>
                        <option {{ old('method', $log->method) == 'options' ? 'selected' : '' }} value="OPTIONS">OPTIONS</option>
                    </select>

                    <input type="text" class="form-control" id="request_url" name="request_url" value="{{ $log->endpoint_url ?? '' }}" required>
                    <button type="submit" style="background-color: #1F3BB3; color:white;" class="btn btn-primary button" onclick="this.classList.toggle('button--loading')"><span class="button__text">GO</button>
                </div>
                <div><label> Headers: </label>
                    <div>
                        <textarea name="request_headers" id="request_headers">{{ $items ?? '' }}</textarea>
                    </div>

                    <div>
                        <label>Company: {{ $log->company ?? '' }}</label>
                        <input type="hidden" name="method_name" value=" {{ $log->method_name ?? '' }} "></input>
                        <input type="hidden" name="type" value=" {{ $log->transaction_type ?? '' }} "></input>
                        <input type="hidden" name="userProductJourneyId" value="{{ customEncrypt($log->enquiry_id) ?? '' }}"></input>
                        <input type="hidden" name="response_time" id="response_time" value=""></input>
                        <input type="hidden" name="company" id="company" value=" {{ $log->company ?? '' }} "></input><br>
                        <div><label>Use Proxy:</label><input id="is_proxy" name="is_proxy" {{ !empty(config('constants.http_proxy')) ? 'checked' : '' }} type="checkbox" value="check" style="position:relative; width:2%;"></div>
                    </div>
                </div>
                <div style="width: 100%; display: flex;">
                    <div style="width: 50%;">
                        <label for="ic_request">Request:</label> <button type="button" onclick="prettyPrint()" style="width:20%; border:hidden; color:green;">Beautify</button><br>
                        <textarea style="height: 550px;" id="ic_request" name="ic_request" required>{{ $log->request ?? '' }}</textarea>
                    </div>
                    <div style="width: 50%;">
                        <label style=" height:1px; text-align:left;">Response:</label>
                        <button type="button" class="fa fa-download btn-download" style="border:hidden; float:right;"></button>
                        <label id="details" name="details" style="float: right; height:1px; text-align:center;"></label>
                        <button class="btn-custom d-none">Download as Pdf</button>
                        <textarea style="height: 550px;" name="ic_response" id="ic_response"></textarea>
                    </div>
                </div>
            </form>

            <form action="{{route('api.log-document-download')}}" method="post" class="d-none pdf-form">
                <input type="hidden" name="pdfContent">
            </form>
        </div>
    </body>

    <script type="text/javascript">
        function prettyPrint() {
            try{
                var ugly = document.getElementById('ic_request').value;
                var obj = JSON.parse(ugly);
                var pretty = JSON.stringify(obj, undefined, 2);

            }catch(err){}
            if(pretty==null){
                // var section='request';
                // formatXml(ugly, ' ',section);
                alert("We're currently not beautifying XML request.")
            }
            else{
                document.getElementById('ic_request').value = pretty;
            }
        }

        function formatXml(xml, tab,section=null) {
            var formatted = '',
            indent = '';
            tab = tab || '\t';
            xml.split(/>\s*</).forEach(function (node) {
                if (node.match(/^\/\w/)) {
                    indent = indent.substring(tab.length);
                }
                formatted += indent + '<' + node + '>\n';
                if (node.match(/^<?\w[^>]*[^\/]$/)) {
                    indent += tab;
                }
            });

            if(section == 'request'){
                return document.getElementById('ic_request').value = formatted.substring(1, formatted.length-3);
            }else{
                return document.getElementById('ic_response').value = formatted.substring(1, formatted.length-3);
            }
        }

        function hitapi(){
            document.querySelector('.btn-custom').classList.add('d-none');
            const btn = document.querySelector(".button");
            jQuery(".button").prop('disabled', true);
            btn.classList.add("button--loading");

            $.ajax({
                url: "{{ route('api.logReqResponse') }}",
                type:'POST',
                data: $('#cf-form').serialize(),
                success: function(data) {
                    btn.classList.remove("button--loading");
                    jQuery(".button").prop('disabled', false);

                    try{
                        var obj = JSON.parse(data.ic_response);
                        var pretty = JSON.stringify(obj, undefined, 2);
                    }catch(err){}

                    if(pretty==null){
                        formatXml(data.ic_response,' ');
                    }else{
                        document.getElementById('ic_response').value = pretty;
                    }

                    $('#details').text(data.response_details);
                    $('#response_time').val(data.response_time);

                    if (data.isFile) {
                        document.querySelector('[name="pdfContent"]').value = data.ic_response;
                        document.querySelector('.btn-custom').classList.remove('d-none');
                    }

                    if (data.http_code == 200) {
                        $('#details').css('color', 'green');
                    } else {
                        $('#details').css('color', 'red');
                    }
                }
            });
        }
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $(".btn").click(function(e){
                e.preventDefault();

                if($("input[name='method_name']").val() != " Token Generation "){
                    if (confirm("Are you looking to generate a token?")) {
                        $.ajax({
                            url: "{{ route('icTokenGeneration', $log->company) }}",
                            type:'POST',
                            data: $('#cf-form').serialize(),
                            success: function(data) {
                                if(data.status == false){
                                    alert(data.message);
                                }else{
                                    $('#request_headers').val(data.token_data);
                                }
                        hitapi();
                        }
                        });
                    }else{
                        hitapi();
                    }
                } else {
                    hitapi();
                }
            });
        });

        $(document).ready(function() {
            $(".btn-download").click(function(e){
                e.preventDefault();
            const formData = jQuery("#cf-form").serialize();

            $.ajax({
                    url: "{{ route('api.logResponseDownload') }}",
                    type:'POST',
                    data: $('#cf-form').serialize(),
                    success: function(data) {
                        const downloadUrl = new Blob([data.text]);
                        const link = document.createElement('a');
                        link.href = window.URL.createObjectURL(downloadUrl);
                        link.download = data.filename;
                        link.click();
                    }
                });
            });
        });

        onload = ()=> {
            document.querySelector('.btn-custom').addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelector('.pdf-form').submit();
            })
        }
    </script>
</html>
