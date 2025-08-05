@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('IC Calculation')])
@section('content')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<style>
    body {
        font-family: Arial, sans-serif;
    }


    .side-bar {
        background-color: #2196F3;
        color: white;
        padding: 20px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
    }

    .side-bar ul {
        list-style: none;
        padding: 0;
    }

    .side-bar li {
        margin-bottom: 10px;
    }

    .side-bar a {
        color: white;
        text-decoration: none;
    }

    .main-content {
        margin-left: 200px;
        padding: 20px;
    }

    .table-container {
        background-color: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .add-button {
        background-color: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }
</style>


<section>

    <div class="content">
        <h2 class="mb-4 ml-3">Add Keys</h2>
        @if (session('status'))
        <div class="alert alert-{{ session('class') }}">
            {{ session('status') }}
        </div>
        @endif
        <div class="row mb-3">
            <button type="button" class="btn btn-primary m-3" data-toggle="modal" data-target="#exampleModal">
                Edit Label
            </button>

            <!-- model start -->

            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">Edit Label</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="edit-label-form">
                                <input type="hidden" name="label_id" id="editLabelId">
                                <div class="form-group">
                                    <label for="editLabelName">Label</label>
                                    <input type="text" class="form-control" id="editLabelName" name="label_name">
                                </div>
                                <div class="form-group">
                                    <label for="editGroupBy">Group By</label>
                                    <input type="text" class="form-control" id="editGroupBy" name="group_by">
                                </div>
                                <!-- Add more fields as necessary -->
                                <button type="submit" class="btn btn-primary">Save changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- model - end -->


        </div>

    </div>

    <script>
        $(document).ready(function() {
            $('.edit-btn').click(function() {
                var labelId = $(this).data('id');
                var labelName = $(this).data('label');
                var groupBy = $(this).data('group-by');

                // Populate the modal fields with the current values
                $('#editLabelId').val(labelId);
                $('#editLabelName').val(labelName);
                $('#editGroupBy').val(groupBy);

                // Show the modal
                $('#editModal').modal('show');
            });

            // Handle the form submission for editing
            $('#edit-label-form').submit(function(event) {
                event.preventDefault();
                var formData = $(this).serialize();

                $.ajax({
                    type: 'POST',
                    url: '/update-label', // Your API endpoint for updating the label
                    data: formData,
                    success: function(response) {
                        // Update the table row with new data or refresh the table
                        $('#editModal').modal('hide');
                        location.reload(); // Reload the page to see changes
                    },
                    error: function(response) {
                        // Handle error
                        alert('Failed to update label');
                    }
                });
            });
        });
    </script>


</section>
@endsection