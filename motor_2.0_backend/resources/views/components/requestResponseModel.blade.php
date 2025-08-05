<!-- Modal -->
<div class="modal fade" id="modal-{{$loop->iteration}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enquiry ID: {{$log->enquiry_id}}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
            <h5 class="modal-title mb-3">URL : </h5>
                <div class="form-outline y-3">
                    <form class="col s12">
                        <div class="row">
                            <div class="input-field col-12">
                                <input readonly id="url-{{$loop->iteration}}" class="form-control border-0 bg-white" value="{{ $log->endpoint_url }}">
                            </div>
                        </div>
                    </form>
                </div>
                <h5 class="modal-title mb-3">Request</h5>
                <div class="form-outline y-3">
                    <form class="col s12">
                        <div class="row">
                            <div class="input-field col-12">
                                <textarea readonly id="request-{{$loop->iteration}}" rows="15" class="form-control border-0 bg-white">
                                {{ $log->request }}
                                </textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <h5 class="modal-title mb-3">Response</h5>
                <div class="form-outline y-3">
                    <form class="col s12">
                        <div class="row">
                            <div class="input-field col-12">
                                <textarea readonly id="response-{{$loop->iteration}}" rows="15" class="form-control border-0 bg-white">
                                {{$log->response}}
                                </textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="copToClipboard('url-{{$loop->iteration}}')">Copy URL</button>
                <button type="button" class="btn btn-primary" onclick="copToClipboard('request-{{$loop->iteration}}')">Copy Request</button>
                <button type="button" class="btn btn-primary" onclick="copToClipboard('response-{{$loop->iteration}}')">Copy Response</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>