@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Communication Configurator')])

@section('content')
<div class="container mt-5">
    <form action="{{ route('admin.communication-configuration.store') }}" method="POST">
        @csrf
        @method('POST')

        @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        <table class="table table-bordered" id="communicationTable">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Email</th>
                    <th>SMS</th>
                    <th>WhatsApp API</th>
                    <th>WhatsApp Redirection</th>
                    <th>All Button</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pages as $pageName => $channels)
                <tr>
                    <td>{{ $pageName }}</td>
                    <td>
                       <input type="checkbox" name="{{ str_replace(' ', '_', $pageName) }}_email" 
                       {{!($channels['email_is_enable'] ?? false) ? 'disabled' : '' }}
                       {{ $channels['email'] ? 'checked' : '' }}
                       data-reference = '{{$pageName}}'>
                    </td>
                    <td>
                        <input type="checkbox" name="{{ str_replace(' ', '_', $pageName) }}_sms"
                        {{(!$channels['sms_is_enable'] ?? false) ? 'disabled' : ''}}
                        {{ $channels['sms'] ? 'checked' : '' }}
                        data-reference = '{{$pageName}}'>
                    </td>
                    <td>
                        <input type="checkbox" name="{{ str_replace(' ', '_', $pageName) }}_whatsapp_api"
                            {{ $channels['whatsapp_api'] ? 'checked' : '' }}
                            onclick="toggleWhatsApp(this, '{{ str_replace(' ', '_', $pageName) }}_whatsapp_redirection')"
                            {{(!$channels['whatsapp_api_is_enable'] ?? false) ? 'disabled' : ''}}
                            data-reference = '{{$pageName}}'>
                    </td>
                    <td>
                        <input type="checkbox" name="{{ str_replace(' ', '_', $pageName) }}_whatsapp_redirection"
                            {{ $channels['whatsapp_redirection'] ? 'checked' : '' }}
                            onclick="toggleWhatsApp(this, '{{ str_replace(' ', '_', $pageName) }}_whatsapp_api')"
                            {{(!$channels['whatsapp_redirection_is_enable'] ?? false) ? 'disabled' : ''}}
                            data-reference = '{{$pageName}}'>
                    </td>
                    <td>
                        <input type="checkbox" name="{{ str_replace(' ', '_', $pageName) }}_all_btn"
                            {{ ($channels['all_btn'] ?? false) ? 'checked' : '' }}
                            data-reference = '{{$pageName}}' id="AllBtns">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </div>
    </form>
</div>
@endsection

<style>
    .small-italic-title {
        font-size: 24px;
        font-style: bold;
        font-weight: normal;
        text-transform: none;
        font-family: "Georgia", serif;
        color: #333;
        margin: 20px 0;
    }
</style>

<script>
    function toggleWhatsApp(currentCheckbox, otherCheckboxName) {
        if (currentCheckbox.checked) {
            document.querySelector(`input[name='${otherCheckboxName}']`).checked = false;
        }
    }

    function validateAllbtns(e)
    {
        let ref = e.getAttribute('data-reference')
        if (ref) {
            let selector = `#communicationTable input[data-reference='${ref}']:checked:not(#AllBtns)`;
            let allRefRow = document.querySelectorAll(selector)
            let allBtn = document.querySelector(`#communicationTable input[data-reference='${ref}']#AllBtns`)
            
            if (allRefRow && allRefRow.length > 1) {
                allBtn.removeAttribute('disabled')
            } else {
                allBtn.checked = false;
                allBtn.setAttribute('disabled', 'disabled')
            }
        }
    }

    onload = ()=> {
        const allCheckBoxes = document.querySelectorAll('#communicationTable input[type="checkbox"]');
        
        allCheckBoxes.forEach(element => {
            element.addEventListener('change', (e) => validateAllbtns(e.target))
        });

        allCheckBoxes.forEach(element => {
            validateAllbtns(element)
        });
    }
</script>