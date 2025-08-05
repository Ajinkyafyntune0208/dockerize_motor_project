<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry ID: {{ $log->enquiry_id ?? '' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .copy-btn {
            cursor: pointer;
            float: right;
            padding: 5px 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
        }
        .copy-btn:hover { background: #0056b3; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        .json-formatter { color: #333; }
        .action-buttons { position: absolute; right: 20px; top: 20px; }
        .xml-formatter {
    color: #333;
    white-space: pre-wrap;
}
.xml-tag { color: #881391; }
.xml-attr { color: #994500; }
.xml-text { color: #1a1a1a; }
.json-key { color: #881391; }
.json-string { color: #268BD2; }
.json-number { color: #268BD2; }
.json-boolean { color: #268BD2; }

    </style>
</head>
<body>
    <!-- Add this right after the opening body tag -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
    <div id="copyAlert" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i> Copied to clipboard!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Replace the existing clipboard initialization in your script with this updated version -->
    <div class="container-fluid">
        <div class="row mb-4 p-2">
            <div class="col-12 d-flex justify-content-end">
            <div class="action-buttons">
                @can('log.edit')
                <a target="_blank" href="{{ route('api.logs.response', ['type' => $log->transaction_type, 'id' => $log->id, 'view' => "Show-Request", 'enc' => enquiryIdEncryption($log->id), 'with_headers' => 1])}}"
                    class="btn btn-success btn-sm me-2">
                    Try It!
                </a>
                @endcan
                <a target="_blank" href="{{ route('api.logs.view-download', ['type' => $log->transaction_type, 'id' => $log->id, 'view' => "download",'enc' => enquiryIdEncryption($log->id), 'with_headers' => 1]) }}"
                    class="btn btn-success btn-sm">
                    Download
                </a>
            </div>
            </div>
        </div>

        <!-- Basic Information Card -->
       

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><b>Trace Id:</b> {{ isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : ''}}</div>
                        <div class="col-md-3"><b>Section:</b> {{ $log->section ?? '' }}</div>
                        <div class="col-md-3"><b>Method Name:</b> {{ $log->method_name ?? '' }}</div>
                        <div class="col-md-3"><b>Company:</b> {{ $log->company ?? '' }}</div>
                    </div>
                </div>
            </div>

        <!-- Vehicle Details Card -->
        @php
            $quote_details = json_decode($log->vehicle_details, true)['quote_details'] ?? '';
            $vehicleRegisterDate = $log->corporate_details->vehicle_register_date ?? '';
        @endphp
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Vehicle Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><b>Version ID:</b> {{ $quote_details['version_id'] ?? '' }}</div>
                    <div class="col-md-4"><b>Registration No:</b> {{ $log->user_proposal_details->vehicale_registration_number ?? $quote_details['vehicle_registration_no'] ?? '' }}</div>
                    <div class="col-md-4"><b>Registration Date:</b> {{ $vehicleRegisterDate ?? '' }}</div>
                    <div class="col-md-4"><b>Fuel Type:</b> {{ $quote_details['fuel_type'] ?? '' }}</div>
                    <div class="col-md-8"><b>Make And Model:</b> {{ ($quote_details['manfacture_name']  ?? '') . '  ' . ($quote_details['model_name'] ?? '') . '  ' . ($quote_details['version_name'] ?? '') }}</div>
                </div>
            </div>
        </div>

        <!-- API Details Card -->
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="card-title mb-0">API Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <b>Response Time:</b> {{$log->response_time}}
                    <button class="copy-btn" data-clipboard-text="{{$log->response_time}}">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="mb-3">
                    <b>Created At:</b> {{ isset($log->created_at) && !empty($log->created_at) ? date('d-M-Y h:i:s A', strtotime($log->created_at)) : ''}}
                </div>
                <div class="mb-3">
                    <b>Request URL:</b> {{ $log->endpoint_url ?? '' }}
                    <button class="copy-btn" data-clipboard-text="{{ $log->endpoint_url ?? '' }}">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="mb-3">
                    <b>Request Method:</b> {{ $log->method ?? '' }}
                    <button class="copy-btn" data-clipboard-text="{{ $log->method ?? '' }}">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="mb-3">
                    <b>Headers:</b>
                    <button class="copy-btn" data-clipboard-text="{{ $log->headers ?? '' }}">
                        <i class="fas fa-copy"></i>
                    </button>
                    <pre class="headers-formatter">{{ $log->headers ?? '' }}</pre>
                </div>
            </div>
        </div>

        <!-- Request Card -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Request</h5>
            </div>
            <div class="card-body">
                <button class="copy-btn" data-clipboard-text="{{ $log->request ?? '' }}">
                    <i class="fas fa-copy"></i>
                </button>
                <pre id="request-content" class="request-formatter">{{ $log->request ?? '' }}</pre>
            </div>
        </div>

        <!-- Response Card -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Response</h5>
            </div>
            <div class="card-body">
                <button class="copy-btn" data-clipboard-text="{{ $log->response ?? '' }}">
                    <i class="fas fa-copy"></i>
                </button>
                <pre id="response-content" class="response-formatter">{{ $log->response ?? '' }}</pre>
            </div>
        </div>
    
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
           document.addEventListener('DOMContentLoaded', function() {
        // Initialize clipboard.js
        const clipboard = new ClipboardJS('.copy-btn');
    const toast = new bootstrap.Toast(document.getElementById('copyAlert'), {
        delay: 2000
    });

    clipboard.on('success', function(e) {
        toast.show();
        e.clearSelection();
    });

    clipboard.on('error', function() {
        const toast = new bootstrap.Toast(document.getElementById('copyAlert'));
        document.querySelector('#copyAlert').classList.remove('bg-success');
        document.querySelector('#copyAlert').classList.add('bg-danger');
        document.querySelector('#copyAlert .toast-body').innerHTML = 
            '<i class="fas fa-times-circle me-2"></i> Failed to copy!';
        toast.show();
    });


        function formatXML(xml) {
            let formatted = '';
            const reg = /(>)(<)(\/*)/g;
            xml = xml.replace(reg, '$1\r\n$2$3');
            let pad = 0;
            
            xml.split('\r\n').forEach(node => {
                let indent = 0;
                if (node.match(/.+<\/\w[^>]*>$/)) {
                    indent = 0;
                } else if (node.match(/^<\/\w/)) {
                    if (pad !== 0) pad -= 1;
                } else if (node.match(/^<\w[^>]*[^\/]>.*$/)) {
                    indent = 1;
                } else {
                    indent = 0;
                }
                
                let padding = '';
                for (let i = 0; i < pad; i++) padding += '    ';
                
                formatted += padding + node + '\r\n';
                pad += indent;
            });
            
            return formatted;
        }

        function highlightXML(str) {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/(".*?")|('.*?')/g, '<span class="xml-attr">$1$2</span>')
                .replace(/&lt;(\/?[\w\s="'-]+)&gt;/g, '<span class="xml-tag">&lt;$1&gt;</span>')
                .replace(/&lt;!\[CDATA\[(.*?)\]\]&gt;/g, '<span class="xml-text">&lt;![CDATA[$1]]&gt;</span>');
        }

        function highlightJSON(str) {
            return str.replace(
                /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
                function(match) {
                    let cls = 'json-number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'json-key';
                        } else {
                            cls = 'json-string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'json-boolean';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                }
            );
        }

        function formatContent(elementId) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const content = element.textContent.trim();
            
            try {
                // Try to parse as JSON first
                const jsonData = JSON.parse(content);
                const formatted = JSON.stringify(jsonData, null, 2);
                element.innerHTML = highlightJSON(formatted);
                element.classList.add('json-formatter');
            } catch (e) {
                // Check if content is XML
                if (content.startsWith('<?xml') || content.startsWith('<')) {
                    try {
                        const formatted = formatXML(content);
                        element.innerHTML = highlightXML(formatted);
                        element.classList.add('xml-formatter');
                    } catch (xmlError) {
                        console.error('XML formatting error:', xmlError);
                        // Keep original content if formatting fails
                        element.textContent = content;
                    }
                } else {
                    // Keep original content if neither JSON nor XML
                    element.textContent = content;
                }
            }
        }

        // Format all content sections
        ['request-content', 'response-content', 'headers-content'].forEach(formatContent);
    });
    </script>
</body>
</html>