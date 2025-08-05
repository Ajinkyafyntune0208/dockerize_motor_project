@extends('admin_lte.layout.app')

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
            <div class="mt-3">
                <div>
                    <h5 class="card-title font-weight-bold">Name</h5>
                    <p class="card-text">{{ $emailSmsTemplate->email_sms_name }}</p>
                </div>
                <hr>
                <div>
                    <h5 class="card-title font-weight-bold">Type</h5>
                    <p class="card-text">{{ $emailSmsTemplate->type }}</p>
                </div>
                <hr>
                <div>
                    <h5 class="card-title font-weight-bold">Subject</h5>
                    <p class="card-text">{{ $emailSmsTemplate->subject }}</p>
                </div>
                <hr>
                <div>
                    <h5 class="card-title font-weight-bold">Variable</h5>
                    <ol>
                        @empty(!$emailSmsTemplate->variable)
                        @foreach($emailSmsTemplate->variable as $variable)
                        <li class="card-text">{{ $variable }}</li>
                        @endforeach
                        @endempty
                    </ol>
                </div>
                <hr>
                <div>
                    <h5 class="card-title font-weight-bold">Body</h5>
                    <iframe class="w-100 h-100 border" style="min-height: 400px;" src="{{ route('admin.show-email-html',$emailSmsTemplate) }}"></iframe>
                </div>
                <hr>
                <div>
                    <h5 class="card-title font-weight-bold">Status</h5>
                    <p class="card-text">{{ $emailSmsTemplate->status }}</p>
                </div>
            </div>
            </iframe>
        </div>
    </div>
</main>
@endsection
@push('scripts')
@endpush