@extends('admin_lte.layout.app', ['activePage' => 'authorization_requests', 'titlePage' => __('Authorization Requests')])
@section('content')
    <div class="card">
        <div class="card-header">
            <div class="success-message"></div>
        </div>
        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Approve/Reject</th>
                        <th>Requested Date</th>
                        <th>Requested By</th>
                        <th>Requested Option</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($request_list as $key => $value)
                        <tr>
                            <td>{{ ++$key }}</td>
                            <td><button class="btn btn-success" id="btnApprove" data-id="{{ $value->authorization_request_id }}">Approve</button>
                            <button class="btn btn-warning" id="btnReject" data-id="{{ $value->authorization_request_id }}">Reject</button></td>
                            <td>{{ $value->requested_date }}</td>
                            <td>{{ $value->request_raised_by }}</td>
                            <td>{{ $value->reference_model }}</td>
                            <td>{{ $value->old_value }}</td>
                            <td>{{ $value->new_value }}</td>
                            <td>{{ $value->request_comment }}</td>
                            

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentModalLabel">Add a Comment for Rejection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="commentTextarea" rows="3" placeholder="Enter your comment"></textarea>
                    <input type="hidden" id="request_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="submitComment">Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection('content')
@section('scripts')
    <script>
        $('#btnApprove').click(function() {
            if (confirm('Are you sure you want to approve this request?')) {                
                var request_id = $(this).data('id');

                $.ajax({
                        url: '/admin/authorization_requests/approve_request',
                        method: 'post',
                        data: {
                            _token: '{{ csrf_token() }}',
                            'request_id':request_id,
                            'request_action':'Approve' 
                        },
                        success: function(response) {
                            console.log('Approved: ', response);
                            if(response=='Success'){
                                $('.success-message').text('Request Approved Successfully.');
                                alert('Request Approved Successfully.');
                                location.reload();
                            }
                            // Additional jQuery code on approval
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                        }
                    });
            }


        });

        $('#btnReject').click(function() {
            $('#commentModal').modal('show');
            $('#request_id').val($(this).data('id'));
        });

        $('#submitComment').click(function() {
            var reject_comment = $('#commentTextarea').val();
            if (confirm('Are you sure you want to reject this request?')) {                
                var request_id = $('#request_id').val();
                $.ajax({
                        url: '/admin/authorization_requests/approve_request',
                        method: 'post',
                        data: {
                            _token: '{{ csrf_token() }}',
                            'request_id':request_id,
                            'request_action':'Reject',
                            'reject_comment':reject_comment 
                        },
                        success: function(response) {
                            console.log('Approved: ', response);
                            if(response=='Success'){
                                $('.success-message').text('Request Rejected Successfully.');
                                alert('Request Rejected Successfully.');
                                location.reload();
                            }
                            // Additional jQuery code on approval
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                        }
                    });
            }


        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
@endsection('scripts')
