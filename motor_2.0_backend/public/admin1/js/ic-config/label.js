$(document).ready(function() {
    function initDataTableWithFilters(tableId) {
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        var table = $(tableId).DataTable({
            info: true,
            dom: 'lPfBrtip',
            pageLength: 10,
            buttons: [],
            columnDefs: [
            { orderable: true, targets: [0, 3] }, // Keep these columns sortable
            { orderable: false, targets: [4, 5, 6] } // Disable sorting for columns 5 and 6
        ],
            order: [
                [0, 'asc']
            ], 
            language: {
                paginate: {
                    next: '<i class="fas fa-angle-right"></i>',
                    previous: '<i class="fas fa-angle-left"></i>' 
                },
                search: '<i class="fas fa-search"></i>'
            }
        });

        var header = $(tableId + ' thead tr');
        header.find('th').each(function(index) {
            if (index !== 4 && index !== 5 && index !== 0 && index !== 6) {
                var column = table.column(index);
                if (index === 1 || index === 2 || index === 3) { 
                    var input = $('<input type="text" class="form-control form-control-sm" placeholder="Search"/>')
                        .appendTo($(this))
                        .on('keyup', function(event) {
                            var value = this.value.trim();
                            if (event.key === 'Enter') {
                                if (value === '') {
                                    var value = this.value.trim();
                                    column.search('').draw();
                                } else {
                                    column.search(value).draw();
                                    table.order([]).draw(); 
                                }
                            }
                        })
                        .on('click', function(event) {
                            event.stopPropagation(); 
                        });
                } else {
                    var select = $('<select class="form-control form-control-sm" style="width: 100%"><option value="">Select</option></select>')
                        .appendTo($(this))
                        .on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? '^' + val + '$' : '', true, false).draw();
                        });
                }
            }
        });
    }
    initDataTableWithFilters('.show-data-label');
    // data table end
    $('#resetButton').on('click', function() {
        $('#create-label')[0].reset();
    });

    $('#create-label').on('submit', function(event) {
        event.preventDefault();

        var label = $('#labelInput').val();
        var key = $('#keyInput').val();
        var data = $(this).serializeArray();
        var group = $('#group_by').val();

        data.push({
            name: '_token',
            value: document
            .querySelector('[name="csrf-token"]')
            .getAttribute("content"),

        });
        data.push({
            name: 'label',
            value: label
        });

        data.push({
            name: 'label_key',
            value: key
        });

        data.push({
            name: 'label_group',
            value: group
        });

        $.ajax({
            url: saveLabel,
            type: "POST",
            data: data,

            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#exampleModal').modal('hide');
                    window.location.href = homePage;
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving data:', error);
            }
        });


    });


  
});

$('.edit-btn').click(function() {
    var label = $(this).attr('label');
    var groupby = $(this).attr('groupby');
    var id = $(this).attr('id');
    var key = $(this).attr('key');
    $('#EditlabelInput').val(label);
    $('#group_by_edit').val(groupby);
    $('#label_id').val(id);
    $('#showKey').text('Label Key : ' + key);
});



$('.delete-btn').click(function() {
    var id = $(this).attr('id');
    if (confirm('Are you sure you want to delete this Label ?')) {
        $.ajax({
            url: deleteLabel,
            type: "DELETE",
            data: {
                id,
                '_token': document
                .querySelector('[name="csrf-token"]')
                .getAttribute("content"),
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = homePage;
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

$('#Editcreate-form').on('submit', function(e) {
    e.preventDefault();
    var data = $(this).serializeArray();
    var labelName = $('#EditlabelInput').val();
    var labelGroup = $('#group_by_edit').val();
    data.push({
        name: '_token',
        value: document
        .querySelector('[name="csrf-token"]')
        .getAttribute("content"),

    });
    data.push({
        name: 'label name',
        value: labelName

    });
    data.push({
        name: 'label group',
        value: labelGroup,

    });
    $.ajax({
        url: editLabel,
        type: "POST",
        data: data,
        success: function(response) {
            if (response.success) {
                $('#editModal').modal('hide');
                alert(response.message);
                window.location.href = homePage;
            } else {
                alert(response.message);
                // $('#editModal').modal('hide');
            }
        }
    });
});

$('#labelInput').keyup(function() {
    var value = $(this).val();
    var newValue = value.replace(/[^a-zA-Z0-9\s]/g, '');
    if (value !== newValue) {
        $(this).val(newValue);
        alert('Special characters are not allowed.');
    }
    var key = this.value.replace(/\s+/g, '_').toLowerCase();
    $('#keyInput').val(key);
});

$('#keyInput').keyup(function() {
    $('#keyInput').keyup(function() {
        var value = $(this).val();
        var newValue = value.replace(/[^a-zA-Z0-9_]/g, ''); // Allow alphanumeric, numeric, and underscore
        if (value !== newValue) {
            $(this).val(newValue);
            alert('Only alphanumeric characters, numbers, and underscores are allowed.');
        }
    });
});

$('#EditlabelInput').keyup(function() {
    var value = $(this).val();
    var newValue = value.replace(/[^a-zA-Z0-9\s]/g, '');
    if (value !== newValue) {
        $(this).val(newValue);
        alert('Special characters are not allowed.');
    }
});