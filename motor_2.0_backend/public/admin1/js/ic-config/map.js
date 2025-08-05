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
            columnDefs: [{
                    orderable: true,
                    // targets: [0, 2]
                }, // Keep these columns sortable
                {
                    orderable: false,
                    targets: [1, 2, 3, 4, 5]
                } // Disable sorting for columns 5 and 6
            ],
            order: [
                [0, 'asc']
            ],
            language: {
                paginate: {
                    next: '<i class="fas fa-angle-right"></i>',
                    previous: '<i class="fas fa-angle-left"></i>'
                },
                search: '<i class="fas fa-search"></i>',
                // lengthMenu: 'Display _MENU_ records'
            }
        });

        var header = $(tableId + ' thead tr');
        header.find('th').each(function(index) {
            if (index != 0 && index != 5) {
                var column = table.column(index);
                if (index === 1 || index === 2 || index === 3 || index === 4) {
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
    initDataTableWithFilters('.map-attribute-datatable');
    //end data table
    $('#create-label').on('submit', function(event) {
        event.preventDefault();
        var data = $(this).serializeArray();
        data.push({
            name: '_token',
            value: document
            .querySelector('[name="csrf-token"]')
            .getAttribute("content")
        });

        $.ajax({
            url: saveAttributes,
            type: "POST",
            data: data,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $("#create-label").trigger('reset');
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving data:', error);
            }
        });
    });

    $('.get-value').on('change', function() {
        var currentElement = $(this).attr('id');
        if (currentElement == 'selectIC') {
            $("#vehicle-data").empty();
            $('#vehicle-data').append('<option value="">Select</option>');
            $("#vehicle-data option[value='']").attr("selected", null);
        }
        if (currentElement == 'selectIC' || currentElement == 'vehicle-data') {
            $("#businessType").empty();
            $('#businessType').append('<option value="">Select</option>');
            $("#businessType option[value='']").attr("selected", null);
        }
        if (currentElement == 'selectIC' || currentElement == 'vehicle-data' || currentElement == 'businessType') {
            $("#attributes").empty();
            $('#attributes').append('<option value="" selected=true disabled=true>select</option>');
        }
        $('#attributes').selectpicker('refresh')
        var selectIC = $('#selectIC').val();
        var vehicle = $('#vehicle-data').val();
        var businessType = $('#businessType').val();
        if (selectIC != '' || businessType != '' || vehicle != '') {
            $.ajax({
                url:getAttribute,
                type: "GET",
                data: {
                    selectIC,
                    businessType,
                    vehicle
                },
                success: function(response) {
                    if (response.msg == 'No Attribute Found') {
                        alert(response.msg);
                    }
                    var attributeSelect = $('#attributes');
                    var segment = $('#vehicle-data');
                    var bussiness = $('#businessType');
                    var setDefault = true; 
                    $.each(response, function (index, value) {
                        if (value.segment) {
                            var optionText = value.segment;
                            if (setDefault === true) {
                                $("#vehicle-data").empty();
                                $('#vehicle-data').append('<option value="">Select</option>');
                                setDefault = false;
                            }
                            segment.append('<option value="' + optionText + '">' + optionText + '</option>');
                        }

                        if (value.business_type) {
                            var bus = value.business_type;
                            if (setDefault == true) {
                                $("#businessType").empty();
                                $('#businessType').append('<option value="">Select</option>');
                                setDefault = false;
                            }
                            // bussiness.append('<option value="">Select</option>');
                            bussiness.append('<option value="' + bus + '">' + bus + '</option>');
                        }

                        if (value.final_attribute && value.id) {
                            var attr = value.final_attribute;
                            var attr_id = value.id;
                            if (setDefault == true) {
                                $("#attributes").empty();
                                $('#attributes').append('<option value="" selected=true disabled=true>select</option>');
                                setDefault = false;
                            }
                            attributeSelect.append('<option value="' + attr_id + '">' + attr + '</option>');
                        }
                    });
                    $('#attributes').selectpicker('refresh')
                },
                error: function(xhr, status, error) {
                    console.error('Error saving data:', error);
                }
            });
        }
    });


    $('.get-value-data').on('change', function() {

        var currentElement = $(this).attr('id');
        if (currentElement == 'selectIC-edit') {
            $("#vehicle-edit").empty();
            $('#vehicle-edit').append('<option value="">Select</option>');
            $("#vehicle-edit option[value='']").attr("selected", null);
        }
        if (currentElement == 'selectIC-edit' || currentElement == 'vehicle-edit') {
            $("#businessType-edit").empty();
            $('#businessType-edit').append('<option value="">Select</option>');
            $("#businessType-edit option[value='']").attr("selected", null);
        }
        if (currentElement == 'selectIC-edit' || currentElement == 'vehicle-edit' || currentElement == 'businessType-edit') {
            $("#editattributes").empty();
            $('#editattributes').append('<option value="">Select</option>');
            $("#editattributes option[value='']").attr("selected", null);
        }

        var selectIC = $('#selectIC-edit').val();
        var businessType = $('#businessType-edit').val();
        var vehicle = $('#vehicle-edit').val();

        if (selectIC != '' || businessType != '' || vehicle != '') {
            $.ajax({
                url: getAttribute,
                type: "GET",
                data: {
                    selectIC,
                    businessType,
                    vehicle
                },
                success: function(response, defaultValue) {
                    if (response.msg == 'No Attribute Found') {
                        alert(response.msg);
                    }
                    var attributeSelect = $('#editattributes');
                    var segment = $('#vehicle-edit');
                    var bussiness = $('#businessType-edit');

                    $.each(response, function(index, value) {
                        if (value.segment) {
                            var optionText = value.segment;
                            segment.append('<option value="' + optionText + '">' + optionText + '</option>');
                        }

                        if (value.business_type) {
                            var bus = value.business_type;
                            bussiness.append('<option value="' + bus + '">' + bus + '</option>');
                        }

                        if (value.final_attribute && value.id) {
                            var attr = value.final_attribute;
                            var attr_id = value.id;
                            attributeSelect.append('<option value="' + attr_id + '">' + attr + '</option>');
                        }
                    });
                },
                error: function(xhr, status, error) {}
            });
        }
    });

    $('.editModal').on('click', function() {
        var selectIC = $(this).attr('company_id');
        var vehicle = $(this).attr('vehicle');
        var businessType = $(this).attr('business');
        var preAttribute = $(this).attr('attribute_id');
        var attribute = $(this).attr('attributeList');

        $.ajax({
            url: getEditAttribute,
            type: "GET",
            data: {
                selectIC,
                businessType,
                vehicle
            },
            success: function(response) {
                var attributeSelect = $('.attributeName');
                $.each(response, function(index, value) {
                    if (value.final_attribute && value.id) {
                        var optionText = value.final_attribute;

                        if (attribute == optionText) {
                            attributeSelect.append('<option value="' + value.id + '" selected>' + optionText + '</option>');

                        } else {
                            attributeSelect.append('<option value="' + value.id + '">' + optionText + '</option>');

                        }
                    }
                });
            }
        });

        $('.companyName').val(selectIC);
        $('.vehicleName').val(vehicle);
        $('.businessName').val(businessType);
        $('#pre-attribute_id').val(preAttribute);
        $('#editattributes').val(attribute);
    });

    $('#save-editForm').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serializeArray();
        data.push({
            name: '_token',
            value: document
            .querySelector('[name="csrf-token"]')
            .getAttribute("content")

        });
        $.ajax({
            url: editAttribute,
            type: "POST",
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
        var id = $(this).attr('id');
        if (confirm('Are you sure you want to delete this Attribute ?')) {
            $.ajax({
                url: deleteAttribute,
                type: "DELETE",
                data: {
                    id,
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

});