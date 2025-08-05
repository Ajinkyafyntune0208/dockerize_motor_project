$(document).ready(function() {
        var table = $(".table-placeholder").DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "scrollX": false,
        }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
        
            $('#create-label').on('submit', function(event) {
                event.preventDefault();
                var data = $(this).serializeArray();
                console.log(data);
                data.push({
                    name: '_token',
                    value: document
                    .querySelector('[name="csrf-token"]')
                    .getAttribute("content")
                });

                $.ajax({
                    url: savePlaceholder,
                    type: "GET",
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                            $('#create-label')[0].reset();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving data:', error);
                    }
                });
            });



            $('.editModal').on('click', function() {
                var name = $(this).attr('pName');
                var key = $(this).attr('kName');
                var id = $(this).attr('data-id');

                $('#holder-name').val(name);
                $('#holder-key').val(key);
                $('#holderID').val(id);
            });

            $('#save-editForm').on('submit', function(e) {
                e.preventDefault();
                var data = $(this).serializeArray();
                console.log(data);
                data.push({
                    name: '_token',
                    value: document
                    .querySelector('[name="csrf-token"]')
                    .getAttribute("content")

                });
                $.ajax({
                    url: editPlaceholder,
                    type: "GET",
                    data,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#editModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.message);
                            $('#editModal').modal('hide');
                        }
                    }
                });
            });

            $('.delete-btn').click(function() {
                var Attribute_id = $(this).attr('attribute_id');

                if (confirm('Are you sure you want to delete this data ?')) {
                    $.ajax({
                        url:deletePlaceholder,
                        type: "DELETE",
                        data: {
                            Attribute_id,
                            '_token': document
                            .querySelector('[name="csrf-token"]')
                            .getAttribute("content")
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                location.reload();
                            } else {
                                alert(response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error saving data:', error);
                        }
                    });
                }
            });

            $('.myModalShow').click(function () {
                var Attribute_id = $(this).attr('attribute_id');
                var name = $(this).attr('pName');
                var key = $(this).attr('kName');

                $('.kname').text(key);
                $('.pname').text(name);

                $.ajax({
                    url: showPlaceholder,
                    type: "GET",
                    data: {
                        Attribute_id,
                        '_token': document
                            .querySelector('[name="csrf-token"]')
                            .getAttribute("content")
                    },
                    success: function (response) {
                        if (response.success) {
                            $('.dataListFormula').empty();
                            $.each(response.data, function(index, value) {
                                var listItem = '<li>';
                                listItem += '' + value.formula_name + '<br>';
                                listItem += '</li>';
                                $('.dataListFormula').append(listItem);
                            });
                        } else {
                            $('.dataListFormula').empty();
                            var noRecordMessage = '<li>No record found</li>';
                            $('.dataListFormula').append(noRecordMessage);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Error saving data:', error);
                    }
                });
            });

            $('#holderName').keyup(function() {
                var value = $(this).val();
                var newValue = value.replace(/[^a-zA-Z0-9\s]/g, '');
                if (value !== newValue) {
                    $(this).val(newValue);
                    alert('Special characters are not allowed.');
                }
                var key = this.value.replace(/\s+/g, '_').toLowerCase();
                $('#holderKey').val(key);
            });

            $('#holder-name').keyup(function() {
                var value = $(this).val();
                var newValue = value.replace(/[^a-zA-Z0-9\s]/g, '');
                if (value !== newValue) {
                    $(this).val(newValue);
                    alert('Special characters are not allowed.');
                }
                var key = this.value.replace(/\s+/g, '_').toLowerCase();
                $('#holder-key').val(key);
            });

        });